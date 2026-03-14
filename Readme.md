# FeatherTrend

Visualize bird phenology directly from sets of pictures

## Data ingestion
Starting from a set of timestamped pictures, we identify the bird species with [Birder](https://gitlab.com/birder/birder).

```bash
# Create Python virtualEnv
cd data-pipeline
python3 -m venv env
source ./env/bin/activate

# Install the birder project
pip install birder

# Download a model; This one seems to work OK on EU birds. Any model compatible with the Birder project can be used.
# it will be saved in models/<name>.pt
python -m birder.tools download-model uniformer_s_eu-common
# Run the classifier
# Results will be saved in results/<model_name + run timestamp>.csv
birder-predict -n uniformer_s -t eu-common --save-output </path/to/images>

# Review the CSV to make sure the classification worked well.
# TODO: use another model to identify which pictures are actually bird pictures...

# Run the Python utility to transform CSV to SQL for insertion into the DB. Adjust the threshold according to your review
./csv2sql.py --threshold 0.6 results/<results>.csv > insert.sql
```

## WebApp

The Php/Symfony backend serves the species list, count per month and pictures. It uses a postgresql DB, according to the `DATABASE_URL` env var.

### Run locally
To run locally, setup your database as wanted, [install Symfony](https://symfony.com/doc/current/setup.html) and run `symfony server:start`.

There is a simple HTML frontend that consumes these endpoints:

![Webapp mobile screenshot](webapp_screenshot.png)


### Live demo
See a [live demo](https://feathertrend.gregoiredelannoy.fr)