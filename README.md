# repo-filechecker 

## Functionality

* Analyzes the data structure and creates a json/ndjson output providing:
  * Files list
  * Directory list
  * File type list
  * Errors list
* Can also create HTML reports from the generated JSON file.
* When run as a docker container, performs antivirus check on files.

### Implemented error checks

* File and directory names don't contain forbidden characters.
* File extension matches MIME type deteced based on the file content
  (MIME-extensions mapping based on the [PRONOM database](http://www.nationalarchives.gov.uk/aboutapps/pronom) with some tuning for not fully reliable content-based MIME type recognition).
* MIME type of a file must be accepted by the ARCHE (as reported by the [arche-assets](https://github.com/acdh-oeaw/arche-assets/)).
* Text files don't contain the [byte order mark](https://en.wikipedia.org/wiki/Byte_order_mark).
* [BagIt](https://en.wikipedia.org/wiki/BagIt) archives are correct (based on checks performed by the [whikloj/bagittools](https://github.com/whikloj/BagItTools) library; bagit archives can be uncompressed of zip/tar gz/tar bz2 files).
* ZIP, XLSX, DOCX, ODS, ODT and PDF files aren't password protected.
  * To avoid memory limit problems only files up to a configuration-determined size are checked.
* XML files provide XML declaration and schema declaration and validate against the schema.
* Image files aren't corrupted.
* No duplicated files (compared by hash).
* No filenames conflicts on case-insensitive filesystems.
		  
## Installation

### Locally

* Install PHP 8 and [composer](https://getcomposer.org/)
* Run:
  ```bash
  composer require acdh-oeaw/repo-file-checker
  ```

### As a docker image

* Install [docker](https://www.docker.com/).

### On ACDH Cluster

Nothing to be done. It is installed there already.

## Usage

### On ACDH cluster

First, get the arche-ingestion workload console by:

* Opening [this link](https://rancher.acdh-dev.oeaw.ac.at/dashboard/c/c-m-6hwgqq2g/explorer/apps.deployment/arche-ingestion/arche-ingestion)
  (if you are redirected to the login page, open the link once again after you log in)
* Clicking on the bluish button with three vertical dots in the top-right corner of the screen and and choosing `> Execute Shell`

Then:

* filechecker
  ```bash
  /ARCHE/vendor/bin/arche-filechecker --csv --html directoryToBeProcessed directoryToWriteReportsInto
  ```
* virus scan
  ```bash
  clamscan --infected directoryToScan
  ```

### Locally

```bash
vendor/bin/arche-filechecker --csv --html directoryToBeProcessed directoryToWriteReportsInto
```

Remarks:

* You can test if the check was successful by reading the exit code of the `vendor/bin/arche-filechecker` command.
  `0` indicates a successful check and non-zero value that at least one error was found.
* To get a list of all available parameters run:
  ```bash
  vendor/bin/arche-filechecker --help
  ```
* If you have [bagit](https://en.wikipedia.org/wiki/BagIt) files, place them into a folder called `bagit` and also compress them into a tgz file.

### As a docker container

* Consider downloading fresh signatures for the antivirus software
  ```bash
  python3 -m pip install --user cvdupdate
  cvd update
  ```
  * If you're running it inside a CI/CD workflow and don't want to be a bad guy causing unnecessary load on the server storing the signature, store the downloaded database in a cache,
    e.g. on Github Actions you may perform the db update using following build steps:
    ```yaml
    - name: cache AV database
      id: avdb
      uses: actions/cache@v3
      with:
        path: ~/.cvdupdate
        key: constant
    - name: refresh AV database
      run: python3 -m pip install --user cvdupdate && cvd update
    ```
* Run a container with the filechecker mounting input and output directories from host:
  ```bash
  docker run \
    --rm \
    -v <pathToReportsDir>:/reports \
    -v <pathToDirectoryToBeProcessed>:/data \
    -v ~/.cvdupdate/database/:/var/lib/clamav \
    acdhch/arche-filechecker
  ```
  e.g.
  ```bash
  docker run \
    --rm --user $UID \
    -v MY_REPORTS_DIR:/reports \
    -v MY_DATA_DIR:/data \
    -v ~/.cvdupdate/database/:/var/lib/clamav \
    acdhch/arche-filechecker --csv --html
  ```

Remarks:

* You can test if the check was successful by reading the exit code of the `docker run` command.
  `0` indicates a successful check and non-zero that at least one error was found.
* If you're processing data in parts you can save some time by running the container in the daemonized mode.
  That way you can avoid loading the virus signatures database every time you run the check. The database load takes 2-5 seconds.
  In the daemonized setup:
    * Run the container with
      ```bash
      docker run \
        --rm -d \
        --name filechecker \
        -v `pwd`/MY_REPORTS_DIR:/reports \
        -v `pwd`/MY_DATA_DIR:/data \
        -v ~/.cvdupdate/database/:/var/lib/clamav \
        -e DAEMONIZE=1 \
        acdhch/arche-filechecker
      ```
    * Wait a few seconds for the AV software to load the viruses database (you can look at docker logs to check if it's ready).
    * Perform the checks with
      ```bash
      # virus check
      docker exec filechecker clamdscan --infected /data
      # filechecker check
      docker exec --user $UID filechecker /opt/filechecker/bin/arche-filechecker --csv --html /data /reports
      ```

## Test Files:

Test files are stored in the `tests/data` folder.
