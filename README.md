# repo_file_checker 

## Updated: 2022-11-21

## Functionality:

* Analyzes the data structure and creates a json/ndjson output providing:
  * Files list
  * Directory list
  * File type list
  * Errors list
* Can also create HTML reports from the generated JSON file.
* When run as a docker container, performs antivirus check on files.

### Implemnted error checks

* Compare the MIME type and the extension (MIMEtypes -> PRONOM: http://www.nationalarchives.gov.uk/aboutapps/pronom)
* File name validation (contains spaces or special characters)
* Directory name validation (contains spaces or special characters)
* Password protected ZIP files checking.
* Password protected xlsx and docx checking based on the MIME type.
* Password protected PDF checking with FPDI
* PDF and Zip file size limitation in the config.ini (if we want to avoid PHP memory limit errors)
		
## System requirements

* PHP 8 with the fileinfo and zip extensions
* 3rd party libraries to be installed by the composer:
  * PHP progressBAR (https://github.com/guiguiboy/PHP-CLI-Progress-Bar)
  * BagIT PHP checker (https://github.com/scholarslab/BagItPHP)
  * PDFParser - https://github.com/smalot/pdfparser
  * PEAR/Archive_TAR (https://github.com/pear/Archive_Tar)
  
## Installation

### Locally

* Install PHP 8 with the fileinfo extension
* Obtain the [composer](https://getcomposer.org/) (if you don't have it already).
* Run `composer require acdh-oeaw/repo-file-checker`
* Create temporary directory and directory for the reports.
* Consider downloading the latest PRONOM Droid Signature File from http://www.nationalarchives.gov.uk/aboutapps/pronom/droid-signature-files.htm
  (you can pass the location of the directory containing the file with the `--signatureDir` command line parameter).

### As a docker image

* Install [docker](https://www.docker.com/).

# Usage

## Locally

```bash
vendor/bin/arche-filechecker --tmpDir <pathToTempDir> --reportDir <pathToReportsDir> <pathToDirectoryToBeProcessed> <outputMode>
```
e.g. to run the check on the sample data provided with the package and generate the full output (assuming temporary dir is `MY_TEMP_DIR` and reports dir is `MY_REPORTS_DIR`)
```bash
vendor/bin/arche-filechecker --tmpDir MY_TEMP_DIR --reportDir MY_REPORTS_DIR vendor/acdh-oeaw/repo-file-checker/_testFiles 3
```

Remarks:

* You can test if the check was successful by reading the exit code of the `vendor/bin/arche-filechecker` command.
  `0` indicates a successful check and non-zero value an error.
* To get a list of all available parameters run:
  ```bash
  vendor/bin/arche-filechecker -- --help
  ```
* If you have [bagit](https://en.wikipedia.org/wiki/BagIt) files, place them into a folder called `bagit` and also compress them into a tgz file.
* If you get file info errors while running under Windows, copy the `extensions/php_fileinfo.dll` to your local php extensions directory
  and enable loadint it in your php.ini.

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
        path: .cvdupdate
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
    acdhch/arche-filechecker <outputMode>
  ```
  e.g.
  ```bash
  docker run \
    --rm --user $UID \
    -v MY_REPORTS_DIR:/reports \
    -v MY_DATA_DIR:/data \
    -v ~/.cvdupdate/database/:/var/lib/clamav \
    acdhch/arche-filechecker 3
  ```

Remarks:

* You can test if the check was successful by reading the exit code of the `docker run` command.
  `0` indicates a successful check and non-zero value an error.
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
      docker exec --user $UID filechecker /opt/filechecker/bin/arche-filechecker --tmpDir /tmp --reportDir /reports /data {mode}
      ```

# Test Files:

You can find files for testing in the _testFiles folder. We have files for the following cases:
  - duplicates -> duplicated files
  - goodFiles -> every file is okay, report will not contain any errors
  - pwProtected -> there is a password protected zip file in the folder.
  - wrongContent -> here we removed the PDF text from the PDF file source. And we renamed the gif file to png.
  - wrongFilename -> the filename contains not legal characters
  - wrongMIME -> wrong MIME types

  If you have big size files, then please allow oPCache in your php settings.
  
  If you want to use your own big files for testing, then you can mount your own directory to the VM /home/vagrant/testfiles/ directory, for this you need to do the following steps.

    - Start the VM and press right click on your VM, select Settings from the menu
    - Now select Shared Folders
    - Click on the Transient Folders line and then click on the Folder with a green plus sign icon on the right side of the window.
    - Browse your test files folder in the Folder Path option, below the Folder Name will be the name what we will use inside the VM to mount this directory.
    - Login to the VM and run the following command: mount -t vboxsf the_share_name /a_folder_name (in our example: mount -t vboxsf testfiles /home/vagrant/testfiles/)
Here you can find an image about the steps:
https://user-images.githubusercontent.com/20183307/28311284-72ba849a-6baf-11e7-9b93-f06e89cb12c4.png
  
