CREATE TABLE `barcodeReplacements` (
	  `ID` int(11) NOT NULL auto_increment,
	  `oldBarcode` varchar(64) NOT NULL,
	  `newBarcode` varchar(64) NOT NULL,
	  PRIMARY KEY  (`ID`),
	  KEY `oldBarcode` (`oldBarcode`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
