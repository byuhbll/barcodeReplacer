#!/opt/sirsi/Unicorn/Bin/perl

use lib "ostinato";
use Ostinato::Transaction;

use File::Basename;

#Set up pathing
my $dirInc;
BEGIN {
	$dirInc = dirname(__FILE__) . "/includes";
}

#Use Ostinato to get the needed barcode replacement mappings
my $transactor = new Ostinato::Transaction();
my $mapfile = $transactor->{env}->getPath("temp") . "/" . $transactor->{env}->getEnvId() . ".app.barcodeReplacement.sql";

#Get the barcode changes from Symphony
$transactor->setSource($transactor->{env}->getPath("dumps") . "/all.transactions");
my $barcode_changes = $transactor->extractBarcodeChangeMap();

#Map the barcode changes into a hash for loading
open BARCODE_CHANGES, "<", $barcode_changes;
my %barcode_hash;
my %barcode_hash_reversed;

while(<BARCODE_CHANGES>)
{                                                                                                
	my @barcodes = split /\|/, $_; 
	my $newBarcode = $barcodes[1];
	my $oldBarcode = $barcodes[0];
	$barcode_hash{$oldBarcode} = $newBarcode;
	$barcode_hash_reversed{$newBarcode} .= "$oldBarcode|";

	if(defined($barcode_hash_reversed{$oldBarcode}))
	{   
		my @barcodeUpdates = split /\|/, $barcode_hash_reversed{$oldBarcode};
		foreach(@barcodeUpdates)
		{   
			$barcode_hash{$_} = $newBarcode;
			$barcode_hash_reversed{$newBarcode} .= "$_|";
		}   
		delete($barcode_hash_reversed{$oldBarcode});
	}   
}
close(BARCODE_CHANGES);

#Generate the SQL to load the new hash into the database
open MAPFILE, ">", $mapfile;
while(my ($oldBarcode, $newBarcode) = each %barcode_hash)
{
	print MAPFILE "$oldBarcode|$newBarcode\n";
}
close(MAPFILE);

my $cmd = "cat $mapfile | php $dirInc/loadBarcodeReplacements.php";
system($cmd);
