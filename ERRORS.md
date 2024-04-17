# Errors

Errors reported by the file-checker and how to fix them:

## XML validation

* `Failed to parse the XML file` - the file contains some XML syntax errors.  
  Inspect and fix it or drop it as invalid.
* `Missing XML declaration` - the file misses the XML version and encoding declaration.  
  Such a declaration should look like (the version number may be different)
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  ```
  and be placed at the very beginning of the file.  
  If it is missing, just add it.
  * While the XML declaration is not strictly required by the XML 1.0 specification
    and only become required in XML 1.1, we require it for all XML files stored in the ARCHE
    because it greatly simplifies recognizing a file as an XML one.
* `Encoding not defined` - the file misses the character encoding attribute in its XML declaration.  
   It means the file starts with something like
   ```xml
   <?xml version="1.0">
   ```
   while we require it to also contain information about the character encoding, e.g.
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  ```
  Fix it by adding the `encoding` attribute to the XML declaration.
* `Schema not defined` - the XML file lacks the schema declaration.  
  Schema declaration allows us to check if the XML file conforms to the schema and informs
  a future user about the standard used to organize the data inside the XML
  (e.g. that the XML content is in a given version of the TEI standard).
  While declaring a schema is not a strict must, it should be strongly encouraged.  
  If the shema information is present, it must be declared using the `xml-model` processing directive (see the [W3C standard](https://www.w3.org/TR/xml-model/)).
  It is typically put at the beginning of an XML file just after the XML declaration, e.g. to declare a TEI schema:
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <?xml-model href="http://www.tei-c.org/release/xml/tei/custom/schema/relaxng/tei_all.rng" type="application/xml" schematypens="http://relaxng.org/ns/structure/1.0"?>
  <TEI (...)
  ```
  The file checker handles schemas in the W3C XML Schema and RELAX NG formats.
  Alternatively the schema can be defined using an internal or external DTD declaration, e.g.
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
  <html> (...)
  ```
* `Failed to read schema` - the provided schema location can not be read,
  e.g. the path is wrong if it points to a file or the URL does not fetch correct data if it is and URL.  
  In such a case please check the schema location indicated in the XML file
  (see the `Schema not defined` description) and fix it if needed.
* `Schema validation against *** failed` - the XML file is not in line with the schema it declares.  
  In such a case either the document or its schema declaration should be fixed or the file should be dropped as broken.
