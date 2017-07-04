# repo_file_checker 

# Updated: 04. July. 2017

### The Repo File Checker functions:

  - Creates a Reports in a HTML format.
	* File and Directory List
	* Error List
	* File Type List
  - With the phpMussel library (https://github.com/Maikuolan/phpMussel) it is checking the viruses, file extensions, contents.
  - PHP progressBAR (https://github.com/guiguiboy/PHP-CLI-Progress-Bar)
  - BagIT PHP checker (https://github.com/scholarslab/BagItPHP)
  - Compare the MIME type and the extension
  - Password protected ZIP files checking. The script is trying to extract the zip files.
  - File name validation (contains spaces or special characters)
  - File name duplication checking
  - phpMussel contains a Black and White list for the file extensions, and also a file size limit option. You can change them in the phpMussel/vault/config file.
  - Password protected xlsx and docx checking based on the MIME type.
  - Password protected PDF checking based on the FPDI library (https://github.com/Setasign/FPDI).
  
  
### System requirements:  
  - PHP 7
  - oPCache or similar
  - PEAR
  - PEAR Archive_TAR (https://pear.php.net/package/Archive_Tar/download)
  
  
### Using:
  - install composer components
  - rename the config.ini.sample to config.ini
  - add a temp Directory and a report directory location to the config.ini file.
  - register and create an API key on the "Virus Total API" and add the API key to the phpMussel/vault/config.ini -> vt_public_api_key section. This is necessary for the file virus checking.
  - call the index.php file from the CLI -> php -f .\index.php directory_what_i_want_to_check  
  - if You have bagit files, then please place them into a folder called "bagit" and also please compress your files into a tgz file.

  
### Test Files:
You can find the testFiles in the _testFiles folder. We have test for the following cases:
  - duplicates -> duplicated files
  - goodFiles -> every file is okay, html report will be generating
  - pwProtected -> there is a password protected zip file in the folder.
  - wrongContent -> here We removed the PDF text from the PDF file source. And we renamed the gif file to png.
  - wrongFilename -> the filename contains not legal characters
  - wrongMIME -> wrong MIME types

  If you want to test big size files, then please allow oPCache in your php settings and also change the file size limits in the phpMussel config file.
  
  
# Updates:

#### 04.07.2017
- Error Report css changes
- Lower/uppercase extension problems fixed in the reports.
- Reports filesize section changed, the script is checking the filesize and it will format the value based on the size.
- Duplicate report now contains the duplicated files folders too.


#### 30.06.2017
- password protected PDF file checking.


#### 27.06.2017
- composer added to the project
- bagIT file checker implemented.


#### 23.06.2017
- Directory list extended with the root directory info, Directory Depth and Directory files sum size.
- File Type List formatting changes
- Password protected docx checkings


#### 22.06.2017
- ProgressBar added
- Xlsx pwd file checker
- Reports template generation changed
- New report: list by extension, size, count, sumsize, min/max size, avgsize.
- File list report has now 2 sections: Files/Directories.

