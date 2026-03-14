#!/usr/bin/env python3
"""
Script to read classification CSV and generate SQL INSERT statements for pictures table.
Looks up species_id from the database based on common_name.
Extracts datetime from image EXIF data.
Filters predictions based on probability threshold.
"""

import csv
import sys
import os
import argparse
import logging
import psycopg2
from psycopg2 import sql, Error
from PIL import Image
from PIL.ExifTags import TAGS
from datetime import datetime
from urllib.parse import urlparse


def parse_database_url(database_url):
    """
    Parse DATABASE_URL environment variable in format:
    postgresql://user:password@host:port/database?params
    Returns a dict with db config.
    """
    parsed = urlparse(database_url)
    
    db_config = {
        'host': parsed.hostname or '127.0.0.1',
        'port': parsed.port or 5432,
        'user': parsed.username or 'feathertrend',
        'password': parsed.password or '',
        'database': parsed.path.lstrip('/') if parsed.path else 'feathertrend'
    }
    
    return db_config


# Global state for tracking new species IDs and statements
_new_species_id_counter = None
_new_species_statements = []
_picture_id_counter = None
_species_cache = {}  # Cache for species that were already processed in this run


def initialize_species_id_counter(connection):
    """
    Initialize the counter for new species IDs based on current max ID in database.
    Should be called once at the start of main().
    """
    global _new_species_id_counter
    try:
        cursor = connection.cursor()
        cursor.execute("SELECT MAX(id) FROM species")
        max_id = cursor.fetchone()[0]
        cursor.close()
        _new_species_id_counter = (max_id or 0) + 1
        logging.info(f"Initialized new species ID counter to {_new_species_id_counter}")
    except Error as e:
        logging.error(f"Error initializing species ID counter: {e}")
        _new_species_id_counter = 1


def initialize_picture_id_counter(connection):
    """
    Initialize the counter for new picture IDs based on current max ID in database.
    Should be called once at the start of main().
    Returns the next picture ID to use.
    """
    global _picture_id_counter
    try:
        cursor = connection.cursor()
        cursor.execute("SELECT MAX(id) FROM pictures")
        max_id = cursor.fetchone()[0]
        cursor.close()
        _picture_id_counter = (max_id or 0) + 1
        logging.info(f"Initialized picture ID counter to {_picture_id_counter}")
        return _picture_id_counter
    except Error as e:
        logging.error(f"Error initializing picture ID counter: {e}")
        _picture_id_counter = 1
        return _picture_id_counter


def get_next_picture_id():
    """
    Get the next picture ID and increment the counter.
    """
    global _picture_id_counter
    current_id = _picture_id_counter
    _picture_id_counter += 1
    return current_id


def get_or_create_species_id(connection, common_name):
    """
    Query the database to get species_id by common_name.
    If not found, generates and outputs INSERT statement for new species (with auto-incremented ID).
    No modifications are made to the database.
    Returns the species_id or None if an error occurs.
    """
    global _new_species_id_counter, _new_species_statements, _species_cache
    
    # Check if we've already processed this species in this run
    if common_name in _species_cache:
        logging.debug(f"Species '{common_name}' already in cache with id {_species_cache[common_name]}")
        return _species_cache[common_name]
    
    try:
        cursor = connection.cursor()
        
        # Try to find existing species (read-only query)
        query = "SELECT id FROM species WHERE common_name = %s"
        cursor.execute(query, (common_name,))
        result = cursor.fetchone()
        cursor.close()
        
        if result:
            species_id = result[0]
            _species_cache[common_name] = species_id
            return species_id
        
        # Species not found, generate INSERT statement for new species
        logging.info(f"Species '{common_name}' not found in database. Will generate INSERT statement.")
        
        new_id = _new_species_id_counter
        _new_species_id_counter += 1
        
        # Generate INSERT statement with auto-incremented ID and scientific_name + common_name
        insert_stmt = f"INSERT INTO species (id, scientific_name, common_name) VALUES ({new_id}, '{common_name}', '{common_name}');"
        _new_species_statements.append(insert_stmt)
        
        # Cache the new species ID to avoid duplicate statements
        _species_cache[common_name] = new_id
        
        logging.info(f"Generated INSERT statement for new species '{common_name}' with id {new_id}")
        return new_id
        
    except Error as e:
        logging.error(f"Database error when getting species: {e}")
        return None


def get_new_species_statements():
    """
    Returns the list of generated INSERT statements for new species.
    """
    return _new_species_statements


def get_exif_datetime(image_path):
    """
    Extract datetime from image EXIF data.
    Falls back to file creation datetime if EXIF data not available.
    Returns datetime string in ISO format or None if not found.
    """
    try:
        image = Image.open(image_path)
        exif_data = image._getexif()
        
        if exif_data is not None:
            for tag_id, value in exif_data.items():
                tag_name = TAGS.get(tag_id, tag_id)
                # EXIF DateTime tag ID is 306, or look for 'DateTime'
                if tag_name == 'DateTime':
                    try:
                        # EXIF datetime format is 'YYYY:MM:DD HH:MM:SS'
                        dt = datetime.strptime(value, '%Y:%m:%d %H:%M:%S')
                        return dt.isoformat()
                    except (ValueError, TypeError):
                        pass
        
        # Fallback to file creation datetime
        file_ctime = os.path.getctime(image_path)
        dt = datetime.fromtimestamp(file_ctime)
        return dt.isoformat()
        
    except (IOError, AttributeError) as e:
        print(f"WARNING: Could not read datetime from {image_path}: {e}", file=sys.stderr)
        return None


def main():
    # Parse command line arguments
    parser = argparse.ArgumentParser(
        description='Read bird classification CSV and generate SQL INSERT statements for pictures table'
    )
    parser.add_argument(
        'csv_file',
        help='Path to the CSV file with bird classification results'
    )
    parser.add_argument(
        '--threshold',
        type=float,
        default=0.5,
        help='Probability threshold for including predictions (default: 0.5)'
    )
    
    args = parser.parse_args()
    
    # Configure logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(levelname)s - %(message)s'
    )
    
    # Get database URL from environment variable
    database_url = os.getenv('DATABASE_URL')
    if not database_url:
        logging.error("DATABASE_URL environment variable not set")
        sys.exit(1)
    
    db_config = parse_database_url(database_url)
    
    logging.info(f"Processing CSV file: {args.csv_file}")
    logging.info(f"Using probability threshold: {args.threshold}")
    
    # Connect to database
    try:
        connection = psycopg2.connect(**db_config)
        logging.info("Connected to database successfully")
    except Error as e:
        logging.error(f"Unable to connect to database: {e}")
        sys.exit(1)
    
    # Initialize the new species ID counter
    initialize_species_id_counter(connection)
    
    # Initialize the picture ID counter
    initialize_picture_id_counter(connection)
    
    # Read CSV and generate SQL
    sql_statements = []
    skipped_count = 0
    processed_count = 0
    
    try:
        with open(args.csv_file, 'r', encoding='utf-8') as csvfile:
            reader = csv.reader(csvfile)
            
            # Read header row to get column indices
            header = next(reader)
            sample_idx = header.index('sample')
            prediction_idx = header.index('prediction')
            
            # Find the column index for the predicted species (probability)
            # The probability is in the column matching the prediction name
            
            for row_num, row in enumerate(reader, start=2):
                if len(row) < max(sample_idx, prediction_idx) + 1:
                    continue
                
                image_url = row[sample_idx].strip()
                common_name = row[prediction_idx].strip()
                
                # Skip "Unknown" predictions
                if common_name.lower() == 'unknown':
                    logging.info(f"Skipping row {row_num}: Unknown prediction")
                    skipped_count += 1
                    continue
                
                # Get probability for this prediction from the appropriate column
                try:
                    species_idx = header.index(common_name)
                    probability = float(row[species_idx])
                except (ValueError, IndexError):
                    logging.warning(f"Could not find probability for '{common_name}' in row {row_num}")
                    skipped_count += 1
                    continue
                
                # Check threshold
                if probability < args.threshold:
                    logging.info(f"Row {row_num}: '{common_name}' probability {probability:.4f} below threshold {args.threshold}")
                    skipped_count += 1
                    continue
                
                logging.info(f"Row {row_num}: '{common_name}' probability {probability:.4f}")
                
                # Get or create species_id from database
                species_id = get_or_create_species_id(connection, common_name)
                
                if species_id is not None:
                    # Get next picture ID
                    picture_id = get_next_picture_id()
                    
                    # Extract datetime from EXIF data (with file creation time fallback)
                    exif_datetime = get_exif_datetime(image_url)
                    
                    # Generate SQL INSERT statement with proper escaping for PostgreSQL
                    escaped_url = image_url.replace("'", "''")
                    
                    if exif_datetime:
                        sql_stmt = f"INSERT INTO pictures (id, specie_id, datetime, path) VALUES ({picture_id}, {species_id}, '{exif_datetime}', '{escaped_url}');"
                    else:
                        sql_stmt = f"INSERT INTO pictures (id, specie_id, path) VALUES ({picture_id}, {species_id}, '{escaped_url}');"
                    
                    sql_statements.append(sql_stmt)
                    processed_count += 1
        
        # Print all SQL statements
        print("\n-- Generated SQL INSERT statements for species table")
        for stmt in get_new_species_statements():
            print(stmt)
        
        print("\n-- Generated SQL INSERT statements for pictures table")
        for sql_stmt in sql_statements:
            print(sql_stmt)
        
        logging.info(f"Processed {processed_count} records")
        logging.info(f"Skipped {skipped_count} records")
        logging.info(f"Total SQL statements generated: {len(sql_statements)}")
        
    except FileNotFoundError:
        logging.error(f"CSV file not found: {args.csv_file}")
        sys.exit(1)
    except IOError as e:
        logging.error(f"Error reading file: {e}")
        sys.exit(1)
    finally:
        connection.close()


if __name__ == "__main__":
    main()
