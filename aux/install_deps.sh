#!/bin/bash
#
# Installs external tools the filechecker depends on:
# - DROID file content recognition tool
#   https://www.nationalarchives.gov.uk/information-management/manage-information/preserving-digital-records/droid/
# - veraPDF PFD/A validation tool
#   https://verapdf.org/

CDIR=`dirname $0`
CDIR=`realpath "$CDIR"`

java -version > /dev/null 2>&1
if [ "$?" != "0" ] ; then
    echo "Java is not available. Please install it first."
    exit 1
fi

if [ ! -d "$CDIR/droid" ] ; then
    URL=`curl -s 'https://www.nationalarchives.gov.uk/information-management/manage-information/preserving-digital-records/droid/' | grep 'droid-binary.*all platforms' | head -n 1 | sed -E 's/.*href="([^"]+)".*/\1/'`
    if [ "$URL" == "" ] ; then
        URL="https://tna-cdn-live-uk.s3.eu-west-2.amazonaws.com/documents/droid-binary-6.7.0-bin.zip"
    fi

    echo "### Installing DROID from $URL"

    curl "$URL" > /tmp/droid.zip &&\
    unzip /tmp/droid.zip -d "$CDIR/droid" &&\
    sed -i \
        -e "s|^droidUserDir=.*|droidUserDir='$CDIR/droid/user'|" \
        -e "s|^droidTempDir=.*|droidTempDir='$CDIR/droid/tmp'|" \
        -e "s|^droidLogDir=.*|droidLogDir='$CDIR/droid/log'|" \
        "$CDIR/droid/droid.sh" &&\
    mkdir "$CDIR/droid/user" "$CDIR/droid/tmp" "$CDIR/droid/log" &&\

    if [ "$?" != "0" ] ; then
        echo "Installation failed"
        rm -fR "$CDIR/droid"
    else
        echo "Installation successful"
    fi
    rm -f /tmp/droid.zip

    $CDIR/droid/droid.sh -d
fi

if [ ! -d "$CDIR/verapdf" ] ; then
    echo "### Installing veraPDF"
    curl https://software.verapdf.org/releases/verapdf-installer.zip > /tmp/verapdf.zip &&\
    unzip /tmp/verapdf.zip -d "/tmp/verapdf" &&\
    sed -i -e "s|%INSTDIR%|$CDIR/verapdf|" "$CDIR/verapdfInstallCfg.xml" &&\
    /tmp/verapdf/*/verapdf-install "$CDIR/verapdfInstallCfg.xml"

    if [ "$?" != "0" ] ; then
        echo "Installation failed"
        rm -fR "$CDIR/verapdf"
    else
        echo "Installation successful"
    fi
    rm -fR /tmp/verapdf.zip /tmp/verapdf
fi