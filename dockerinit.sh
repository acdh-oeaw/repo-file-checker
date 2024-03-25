#!/bin/bash
if [ -z "$DAEMONIZE" ] ; then
    echo "### Performing virus scan" &&\
    clamscan --recursive --infected /data &&\
    echo "### Running the filechecker" &&\
    /opt/vendor/bin/arche-filechecker -- /data /reports "$@" &&\
    echo "### Ended successfully"
else
    echo "### Running antivirus in a daemon mode"
    echo "###"
    echo "### First wait a few seconds for the clamd to load virsues database (when you see a 'Self checking every 3600 seconds.' log message, then it's ready)."
    echo "###"
    echo "### Perform virus checks with 'docker exec {containerName} clamdscan --infected /data'"
    echo "###"
    echo "### Run the filechecker with 'docker exec {containerName} /opt/vendor/bin/arche-filechecker /data /reports'"
    echo "###"
    echo "### Run the metadata crawler with 'docker exec {containerName} /opt/vendor/bin/arche-filechecker /metadataPath /outputTtlPath /dataPath IdIriPrefix'"
    echo "###"
    clamd --foreground
fi
