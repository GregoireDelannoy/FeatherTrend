# Data ingestion
Starting from a set of timestamped pictures, we identify the bird species with [Birder](https://gitlab.com/birder/birder) and then generate a set of SQL instructions to populate the Feathertrend DB.

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
