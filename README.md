barcodeReplacer
===============
================================================================================

The barcodeReplacer is a small utility used to track and lookup changed 
barcodes/ItemIDs using the Symphony ILS APIs.

WARNING: THIS UTILITY IS DESIGNED FOR EXPERIENCED USERS ONLY.  
DO NOT USE THIS LIBRARY UNLESS YOU KNOW WHAT YOU ARE DOING, 
AND RESTRICT ACCESS TO TRUSTED USERS ONLY!!

NO WARRANTY OR SUPPORT OF ANY KIND IS PROVIDED FOR THIS SOFTWARE.

Dependencies
------------
The barcodeReplacer is circularly dependent on the Ostinato library 
(https://github.com/byuhbll/ostinato).

Installation and Usage
----------------------
No installation is required to use this software.  Simply drop it somewhere on 
your Symphony server (but NOT in the Unicorn directory) and change the 
"ostinato" symbolic link to point to the location of the Ostinato library.

The barcodeReplacer requires a MySQL database called 'symphony' with a table 
called 'barcodeReplacements' in order to function.  The 
'createBarcodeReplacementsTable' contains the required table structure.

To export barcode changes from the Symphony history logs into the database, 
run the following command:

<pre>
perl exportBarcodeReplacements.pl
</pre>

To lookup barcode changes from the database, run the following command after 
writing the barcodes to a file.  This file should contain 1 barcode per line, 
but may contain other information as well in a pipe-delimited format.  You can
specify the column containing the barcode, or the lookup command will default
to the first column on each line:

<pre>
cat path/to/file/containing/data.txt | php lookupBarcodeReplacements.php column=1
</pre>

License
-------
barcodeReplacer was developed by Brigham Young University and is licensed under 
the Creative Commons Attribution-ShareAlike 3.0 Unported License.  To view a 
copy of this license, visit http://creativecommons.org/licenses/by-sa/3.0/.

Symphony is owned and copyrighted by SirsiDynix.  All rights reserved.
