<?php
/**********************************************************************************
** lookupBarcodeReplacements.php                                                 **
**                                                                               **
** This script will take barcodes (either defined as a "barcode" parameter or    **
** listed in a specified file and look them up in a database table to return     **
** the current barcode associated with that catalog record.                      **
**                                                                               **
**********************************************************************************/

DEFINE("DB_CONFIG_FILE", realpath(dirname(__FILE__)) . "/includes/database.config.php");

//Import needed variables from the command line arguments (simulating URL query strings)
parse_str(implode('&', array_slice($argv,1)), $_GET);

$infile  = isset($_GET['in'])     ? $_GET['in']     : 'php://stdin';
$outfile = isset($_GET['out'])    ? $_GET['out']    : 'php://stdout';
$column  = isset($_GET['column']) ? $_GET['column'] : 0;
$delimiter = isset($_GET['delimiter']) ? $_GET['delimiter'] : '|';
lookupCurrentBarcodes($infile, $outfile, $column, $delimiter);
exit(0);

/** connectToDatabase()
 * 	This function establishes a connection to the database as defined in an imported database configuration file
 * 	
 * 	@param  [none]
 * 	@return [none]
 */
function connectToDatabase()
{
	require_once(DB_CONFIG_FILE);
	mysql_connect(DBHOST, DBUSER, DBPASS) or die ("Unable to connect to DB server.");
	mysql_select_db(DBNAME) or die ("Unable to select DB.");
}

/** lookupCurrentBarcode($barcode)
 *	This function looks up a single barcode in the database and returns the most current barcode associated with that item
 *
 *	@param  $barcode  The barcode to be looked up
 *	@return           $barcode if no replacement was found in the database, or the replacement if one was found
 */
function lookupCurrentBarcode($barcode)
{
	connectToDatabase();

	$query="SELECT newBarcode FROM symphony.barcodeReplacements WHERE oldbarcode ='".mysql_real_escape_string($barcode)."'";
	//return result
	$result=mysql_query($query);
	//select the result
	$row=mysql_fetch_array($result);

	if($row === false)
		return $barcode;
	else
		return $row['newBarcode'];
}

/** lookupCurrentBarcodes($infile, $outfile, $column = 0)
 *	This function will read a pipe-delimited file $infile.  It will extract the data located 
 *	in the specified column, which is assumed to be the barcodes to be checked.  Those barcodes
 *	will be sent to the database (in batches of up to 1000 at once) and the most recent version
 *	of each barcode will be inserted into the pipe-delimited file.  This new pipe-delimited file
 *	will be written to $outfile.
 *
 *	@param $infile	A string defining the path to the file to read
 *	@param $outfile	A string defining the path to the file to write
 *	@param $column	An integer specifying which column in $infile contains the barcode.  If left blank, will default to 0.
 */
function lookupCurrentBarcodes($infile, $outfile, $column = 0, $delimiter = '|')
{
	connectToDatabase();

	//Attempt to open the files, or die if we can't...
	if ( $infile == "php://stdin" || filesize($infile) )
		$inHandle  = fopen ($infile, 'r') or die ("Cannot open file: $infile");
	else
		die("File exists but is empty: $infile");
	$outHandle = fopen($outfile, 'w') or die ("Cannot open file: $outfile");

	//$lineCount simply tracks the lines, while $filedata contains an array'd version of the input file (up to 1000 lines)
	$lineCount = 0;
	$filedata = array();
	//Load each line of the file into the variable $data
	while($data = fgetcsv($inHandle, $lineLength = 0, $delimiter))
	{
		//Every 1000 lines, run the queries, write to the output file, and clear some memory
		if($lineCount >= 999)
		{
			fwrite($outHandle, runBarcodesQuery($filedata, $column, $delimiter));
			$lineCount = 0;
			$filedata = array();
		}	
		//For every line, push the array'd line of data to $filedata and increment our line counter
		array_push($filedata, $data);
		$lineCount++;
	}
	//At the end of the file, perform one last query and write to the output file (this is the only query+write that will occur for files containing less than 1000 lines
	fwrite($outHandle, runBarcodesQuery($filedata, $column, $delimiter));
	#fwrite($outHandle, runBarcodesQuery($listOfBarcodes, $filedata, $column));
}

/** runBarocdesQuery($filedata, $column)
 *	This function is used in conjunction with lookupCurrentBarcodes().  It takes a 2-dimensional 
 *	array, representing multiple lines of data.  For each line of data, it will pull out the Nth
 *	field using the $column to determine the value of N.  The value of that field is assumed to
 *	be the barcode, and will be stored as a hash key (for later lookup) and sent to the database
 *	to see if it has been replaced with a newer barcode.  Since it is faster to lookup all the 
 *	barcodes at once in the database, we will have to make a second pass over the $filedata array.
 *	The barcode hash mapping is then used to replace all barcode values (usually with themselves,
 *	but with a new barcode if it has been replaced).  The modified data lines are returned.
 *
 *	@param  $filedata  The array containing lines of data (each represented by an array of strings)
 *	@param  $column    The index of the field (on each line) containing the barcode
 *	@return            A 2-dimensional array, identical to the param $filedata but having potentially different barcodes 
 */ 
function runBarcodesQuery($filedata, $column = 0, $delimiter = '|')
{
	//Set up some initial variables
	$listOfBarcodes  = '';
	$hashOfBarcodes = array();

	//Extract the barcodes from the data array.  Create a hash containing each barcode as well as a string containing them all
	foreach($filedata as $line)
	{
		$hashOfBarcodes[$line[$column]] = $line[$column];
		$listOfBarcodes .= "'" . $line[$column] . "',";
	}
	$listOfBarcodes = substr($listOfBarcodes,0,-1);

	//Use the created string with all barcodes to query against the database
	$query="SELECT oldBarcode,newBarcode FROM symphony.barcodeReplacements WHERE oldbarcode IN ($listOfBarcodes)";
	$result=mysql_query($query);

	//Since most of our barcodes will probably not appear in the database, we need to insert the results back into the barcode hash, which preserves the order and original barcodes (if they didn't change)
	while($row=mysql_fetch_array($result))
	{
		$hashOfBarcodes[$row['oldBarcode']] = $row['newBarcode'];
	}

	//Insert the updated barcodes back into our data array and rturn a |\n delimited stream that can easily be written to a file and used in other sirsi calls
	$resultString = "";
	foreach($filedata as $line)
	{
		$line[$column] = $hashOfBarcodes[$line[$column]];
		$resultString .= implode($delimiter, $line) . "\n";
	}

	return $resultString;
}

?>
