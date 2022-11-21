#!/bin/bash
if [ -z "$DAEMONIZE" ] ; then
    echo "### Performing virus scan" &&\
    clamscan --recursive --infected /data &&\
    echo "### Running the filechecker" &&\
    php -f /opt/filechecker/index.php -- --tmpDir /tmp --reportDir /reports /data "$@"
    echo "### Ended"
else
    echo "### Running antivirus in a daemon mode"
    echo "###"
    echo "### First wait a few seconds for the clamd to load virsues database (when you see a 'Self checking every 3600 seconds.' log message, then it's ready)."
    echo "###"
    echo "### Perform virus checks with 'docker exec {containerName} clamdscan --infected /data'"
    echo "###"
    echo "### Run the filechecker with 'docker exec {containerName} /opt/filechecker/bin/arche-filechecker --tmpDir /tmp --reportDir /reports /data {mode}'"
    echo "###"
    clamd --foreground
fi
