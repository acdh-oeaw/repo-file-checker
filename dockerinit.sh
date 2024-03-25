#!/bin/bash
if [ -z "$DAEMONIZE" ] ; then
    # https://stackoverflow.com/questions/1215538/extract-parameters-before-last-parameter-in
    if [ "$1" == "crawl" ] ; then
        php -f /opt/vendor/bin/arche-crawl-metadata -- "${@:2}" /mnt
    elif [ "$1" == "createTemplate" ] ; then
        php -f /opt/vendor/bin/arche-create-metadata-template -- /mnt "${@:2}"
    elif [ "$1" == "check" ] ; then
        php -f /opt/vendor/bin/arche-check-metadata -- "${@:2}"
    else
        echo "### Performing virus scan" &&\
        clamscan --recursive --infected /data &&\
        echo "### Running the filechecker" &&\
        /opt/vendor/bin/arche-filechecker -- /data /reports "$@" &&\
        echo "### Ended successfully"
    fi
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
