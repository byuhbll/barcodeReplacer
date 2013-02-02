<?php
/**********************************************************************************
** loadBarcodeReplacements.php                                                   **
**                                                                               **
** This script will take as input a text file containing old and new barcode     **
** mappings and load those mappings into a database table.  Note that the table  **
** will be truncated prior to this process to ensure a clean load.               **
**                                                                               **
**********************************************************************************/

DEFINE("DB_CONFIG_FILE", realpath(dirname(__FILE__)) . "/database.config.php");
DEFINE("DB_TABLE_NAME", "symphony.barcodeReplacements");
DEFINE("COLUMN_OLD_BARCODE", 0);
DEFINE("COLUMN_NEW_BARCODE", 1);

//Import needed variables from the command line arguments (simulating URL query strings)
parse_str(implode('&', array_slice($argv,1)), $_GET);

$infile  = isset($_GET['in'])     ? $_GET['in']     : 'php://stdin';
$delimiter = isset($_GET['delimiter']) ? $_GET['delimiter'] : '|';
connectToDatabase();
loadBarcodeMapping($infile, $delimiter);
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

/** loadBarcodeMapping($infile, $delimiter)
 * 	This function truncates the symphony.barcodeReplacements table and repopulates it using the values stored
 * 	in the file stored at $infile
 * 	
 * 	@param  $infile : The path to the file containing the barcode mapping to be uploaded to the database
 * 	@param  $delimiter : The delimiter used to separate fields in the $infile file
 * 	@return [none]
 */
function loadBarcodeMapping($infile, $delimiter = '|')
{
	//Attempt to open the files, or die if we can't...
	if ( $infile == "php://stdin" || filesize($infile) )
		$inHandle  = fopen ($infile, 'r') or die ("Cannot open file: $infile");
	else
		die("File exists but is empty: $infile");
	
	$query  = "TRUNCATE " . DB_TABLE_NAME;
	$result = mysql_query($query);

	//$lineCount simply tracks the lines, while $filedata contains an array'd version of the input file (up to 1000 lines)
	$lineCount = 0;
	$filedata = array();
	//Load each line of the file into the variable $data
	while($data = fgetcsv($inHandle, $lineLength = 0, $delimiter))
	{
		//Every 1000 lines, run the queries, write to the output file, and clear some memory
		if($lineCount >= 999)
		{
			runBarcodesQuery($filedata, $delimiter);
			$lineCount = 0;
			$filedata = array();
		}	
		//For every line, push the array'd line of data to $filedata and increment our line counter
		array_push($filedata, $data);
		$lineCount++;
	}
	runBarcodesQuery($filedata, $delimiter);
}

/** runBarcodesQuery($filedata, $column)
 *	This function is used in conjunction with loadBarcodeMapping().  It takes a 2-dimensional 
 *	array, representing multiple lines of data.  For each line of data, it will pull out the old
 *	and new barcode, based on the definitions at the top of the file.  This old/new barcode mapping
 *	will then be added to the barcodeReplacement table of the database
 *
 *	@param  $filedata  The array containing lines of data (each represented by an array of strings)
 * 	@param  $delimiter : The delimiter used to separate fields in the $infile file
 *	@return            A 2-dimensional array, identical to the param $filedata but having potentially different barcodes 
 */ 
function runBarcodesQuery($filedata, $delimiter = '|')
{
	//Set up some initial variables
	$listOfBarcodes  = '';

	#Extract the mappings from the incoming array and SQLize the needed INSERT statement
	foreach($filedata as $line)
	{
		if(isset($line[COLUMN_OLD_BARCODE]) && $line[COLUMN_OLD_BARCODE] != '' && isset($line[COLUMN_NEW_BARCODE]) && $line[COLUMN_NEW_BARCODE] != '')
			$listOfBarcodes .= "('" . mysql_real_escape_string($line[COLUMN_OLD_BARCODE]) . "','" . mysql_real_escape_string($line[COLUMN_NEW_BARCODE]) . "'),";
	}
	$listOfBarcodes = substr($listOfBarcodes,0,-1);

	//Use the created string with all barcodes to query against the database
	$query  = "INSERT INTO " . DB_TABLE_NAME . " (oldBarcode, newBarcode) VALUES $listOfBarcodes";
	$result = mysql_query($query);

}

?>
