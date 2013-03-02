PHP-SEPA-files
==============

Creation in PHP of SEPA XML files according pain.008.001.02.
sepaclass.php contains the classes for the generation of the XML file.
MakeSEPAFile.php shows you how you can retrieve data from your database and
use it to create the XML file.
The script is used by our sporting club to upload all our incassos to the Rabobank 
with an XML format file, replacing the depricated CLIEOP format as per February 2014.


Status update:

02Mar13: deleted the old repository, created this new one

01Mar13: the Rabobank accepted the file through telebankieren, most moneys were received
however "postbanknumbers" were all systematically rejected. Rabobank is sorting out what happened.

26Feb13: Used the scripts to generate an XML file and uploaded to Rabobank via telebankieren

24Feb13: uploaded sepaclass24Feb13.php and MakeSEPAFile.php to GITHUB

Before:
Since Feb 2012, various updates in the format to get the XML file accepted by the Rabobank
