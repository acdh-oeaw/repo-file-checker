# repo_file_checker 

# Updated: 29. March. 2018

### The Repo File Checker functions:

	- it is generating json/ndjson output files on the fly:
		* File list
		* Directory List
		* Error List
		* File Type List
	- you can also create HTML reports from the generated JSON file 


### Available error checks
	- Compare the MIME type and the extension
	- Password protected ZIP files checking.
	- File name validation (contains spaces or special characters)
	- Directory name validation (contains spaces or special characters)
	- Password protected xlsx and docx checking based on the MIME type.
	- Password protected PDF checking with FPDI
 	- PDF and Zip file size limitation in the config.ini (if we want to avoid PHP memory limit errors)
		
		
### Used 3rd party libraries		
		
  - PHP progressBAR (https://github.com/guiguiboy/PHP-CLI-Progress-Bar)
  - BagIT PHP checker (https://github.com/scholarslab/BagItPHP)
  - FPDI - https://github.com/Setasign/FPDI
  
  
### System requirements:  
  - PHP 7
  - oPCache or similar
  - PEAR
  - PEAR Archive_TAR (https://pear.php.net/package/Archive_Tar/download)
  - PHP file info extension  
  
  
  
### Using:
  - install composer components
  - rename the config.ini.sample to config.ini
  - add a temp Directory and a report directory location to the config.ini file. And define a zip and pdf size limit in bytes.
  - call the index.php file from the CLI, you have the following options:
	* 0 => check files  (json output) and create file type report (json output)
	* 1 => check files (json output and html output) and create file type report (json output)
	* 2 => check files (NDJSON output)
  
  - if You have bagit files, then please place them into a folder called "bagit" and also please compress your files into a tgz file.
  - if you have file info errors during the run, and you using windows then please copy the "php_fileinfo.dll" from the extensions directory to you local php extensions directory. And please do not forget to add this to your php.ini file extensions part.

  
### Test Files:
You can find the testFiles in the _testFiles folder. We have test for the following cases:
  - duplicates -> duplicated files
  - goodFiles -> every file is okay, html report will be generating
  - pwProtected -> there is a password protected zip file in the folder.
  - wrongContent -> here We removed the PDF text from the PDF file source. And we renamed the gif file to png.
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
  

