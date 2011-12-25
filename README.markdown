# FOP extension for Symphony

* Version: 0.1
* Author: Nanymor <http://github.com/nanymor/>
* Build Date: 2011-12-21
* Requirements: Symphony 2.2

This extension provides a field that saves a pdf version of entries and a pseudo-event that transforms xsl:fo pages into pdf (or possibly other formats)

## Installation
* extract the `fop` folder to your `/extensions` directory
* enable the extension
* create some frontend page that outputs xsl:fo nad give it a "fop" page type
* You can add fop field to a section or output pdf directly from frontend pages 

## Compatibility

Symphony | Entry Versions
------------- | -------------
2.1.* – 2.0.8 | Not tested
2.2.* | [0.1](https://github.com/nanymor/FOP/)

## Usage
First step is to create some frontend page and give it a "fop" page type. The page should output an xsl:fo file as xml.
you can find some xsl:fo docs and and examples at <http://www.dpawson.co.uk/xsl/sect3/index.html> or at the W3C.

Once you have set up the frontend pages you can either call them straight passing a ***pdfname*** url parameter or add it as field to sections:

  1. When called straight it should turn the fo page into a pdf and prompt to download, the pdf file is saved only temporarily on the server.
  2. When used as a field you have options to specify a frontend page to use for pdf creation, a folder for storing the pdf file and a filename, that can be constructed with xpath using other fields as values. When creating or editing an entry the field will call the frontend page passing the entry ID to it, resolve the file name and save the pdf file into the chosen folder.
  

## troubleshooting, limitations etc.
This extension includes FOP, it's a java application that can transform xsl:fo documents into various formats. Fop is called from the command line so in order to make it work your server needs to have Java and CLI working, so forget about running this on shared hosting. Actually for now it's only been tested on a localhost MAMP, as it was primarily developed for it.
If you don't get any output check that fop is working properly by running it from the terminal: /extensions/fop/lib/fop -? should output a help page.
You should also check that fop has execution permissions and that the output folder has write permissions.
Fop is very picky, so if something is not correct in the xsl:fo the transformation will fail.
You can find more info at fop [website](http://xmlgraphics.apache.org/fop/)

## Credits
This extension is mainly based on the reflection field extension by Buzzomatic, with a lot of other copy/pasting from other extensions by  Nickdunn and Bauhouse. I'd like to thank Stephen Bau for pushing me to release it. You are all more than welcome to fork it, make it better, and fix some of the nasty code (I'm originally a designer, so I'm kind of wild at coding...)
