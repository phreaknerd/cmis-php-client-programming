_**Note (2010-09-07): This work has been moved to the [Apache Chemistry Project](http://incubator.apache.org/chemistry/phpclient.html) and future updates to this code will be available there.**_

# Introduction #

I have taken a snapshot of the source as of Dec 1 and zipped it up for download.
This will enable me to start making changes


# Details #

Am now adding CMISService which will map more closely to the Services in the Domain model.  This is a work in progress.  Checked in code will usually be able to run without totally breaking.

Zipped downloads and Labeled revisions should generally run without breaking

# Update 2010-02-15 #
## Added new update with some test code. ##
Filled out quite a bit of the domain level routines including
  * getRepositories
  * getTypeDefinition
  * getChildren
  * getFolderParent
  * getObjectParents
  * getCheckedOutDocs
  * getObject
  * getObjectByPath
  * getProperties
  * getContentStream
  * createDocument
  * createFolder
  * updateProperties
  * moveObject
  * deleteObject
  * setContentStream
  * deleteContentStream

Many of these have been tested but not all -- some known issues:
  * Not handling optional inputs (filters etc....)
  * Some assumptions are being made regarding the form of URLs
  * May be making some assumptions about XML format w.r.t. Name spacing
  * Has only been tested against the Alfresco Repository

# Update 2010-09-07 #
  * Fixed problem with handling of spaces -- no need to urlencode inputs
  * Added new functionality
    * Get Type Children
    * Get Type Descendants
    * Get Folder Tree
    * Get Descendants
  * Updated Documentation