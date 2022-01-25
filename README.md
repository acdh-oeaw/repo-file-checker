# repo_file_checker 

# Updated: 26. May. 2021

### The Repo File Checker functions:

	- it is generating json/ndjson output files on the fly:
		* File list
		* Directory List
		* Error List
		* File Type List
	- you can also create HTML reports from the generated JSON file 

### Available error checks
	- Compare the MIME type and the extension (MIMEtypes -> PRONOM: http://www.nationalarchives.gov.uk/aboutapps/pronom)
	- File name validation (contains spaces or special characters)
	- Directory name validation (contains spaces or special characters)
    - Password protected ZIP files checking.
	- Password protected xlsx and docx checking based on the MIME type.
	- Password protected PDF checking with FPDI
 	- PDF and Zip file size limitation in the config.ini (if we want to avoid PHP memory limit errors)
		
### Used 3rd party libraries		
		
  - PHP progressBAR (https://github.com/guiguiboy/PHP-CLI-Progress-Bar)
  - BagIT PHP checker (https://github.com/scholarslab/BagItPHP)
  - PDFParser - https://github.com/smalot/pdfparser
  
### System requirements:

  - PHP 8
  - PEAR Archive_TAR (https://pear.php.net/package/Archive_Tar/download)
  - PHP file info extension  
  
### Using:

  - Obtain the [composer](https://getcomposer.org/) (if you don't have it already).
  - Run `composer require acdh-oeaw/repo-file-checker`
  - Create temporary directory and directory for the reports.
  - Consider downloading the latest PRONOM Droid Signature File from http://www.nationalarchives.gov.uk/aboutapps/pronom/droid-signature-files.htm
    (you can pass the location of the directory containing the file with the `--signatureDir` command line parameter).
  - Execute the script
    ```bash
    php -f vendor/acdh-oeaw/repo-file-checker/index.php -- --tmpDir <pathToTempDir> --reportDir <pathToReportsDir> <pathToDirectoryToBeProcessed> <outputMode>
    ```
    e.g. to run the check on the sample data provided with the package and generate the full output (assuming temporary dir is `MY_TEMP_DIR` and reports dir is `MY_REPORTS_DIR`)
    ```bash
    php -f vendor/acdh-oeaw/repo-file-checker/index.php -- --tmpDir MY_TEMP_DIR --reportDir MY_REPORTS_DIR vendor/acdh-oeaw/repo-file-checker/_testFiles 3
    ```
  - To get a list of all available parameters run:
    ```bash
    php -f vendor/acdh-oeaw/repo-file-checker/index.php -- --help
    ```
  - if You have bagit files, then please place them into a folder called "bagit" and also please compress your files into a tgz file.
  - If you get file info errors during the run, and you are using Windows then please copy the "php_fileinfo.dll" from the extensions directory to your local php extensions directory.
    Please don't forget to add this to your php.ini file extensions part.

  
### Test Files:

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
  
