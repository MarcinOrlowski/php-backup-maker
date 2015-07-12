#!/usr/bin/php -q
<?php
/* vim: set tabstop=3 shiftwidth=3: */


// don't remove this. I don't expect you see any warning/error in my c00l c0d3{tm} ;-)
error_reporting(E_ALL);

// $Id: pdm.php,v 1.53 2004/08/18 11:00:55 carl-os Exp $
//
// Scans $source_dir (and subdirs) and creates set of CD with the content of $source_dir
//
// Author: Marcin Orlowski <carlos@wfmh.org.pl>
//
// Project home: https://bitbucket.org/borszczuk/php-backup-maker/
//
define( "SOFTWARE_VERSION"			, "5.1.0" );
define( "SOFTWARE_VERSION_BETA"		, false );
define( "SOFTWARE_VERSION_BETA_STR"	, " beta");
define( "SOFTWARE_URL"				, "https://bitbucket.org/borszczuk/php-backup-maker/" );


// argv/argc workaround for register_globals disabled
if( !(isset( $argc )) )	$argc = $_SERVER['argc'];
if( !(isset( $argv )) )	$argv = $_SERVER['argv'];



/***************************************************************************
**
** $Id: pdm.php,v 1.53 2004/08/18 11:00:55 carl-os Exp $
**
** (C) Copyright 2003-2015 * All rights reserved
**     Marcin Orlowski <carlos (AT) wfmh.org.pl>
**
** Function: (C)command (L)ine (I)nterface - shell argument parsing
**           and handling class. Features automatic required arguments
**           detection, valueless switches handling, automatic help
**           page display, unlimited arguments support and more
**
***************************************************************************/

class CLI
{
	var	$args;
	var	$found_args;

	var	$errors;

	var	$version_str = "CLI class 1.3 by Marcin Orlowski <carlos (AT) wfmh.org.pl>";

	// this array describes all the fields args array should define
	// In any of these is missing, we sert up this defaults instead
	var $default_fields = array("short"    => false,
	                            "long"     => false,
	                            "info"     => "--- No description. Complain! ---",
	                            "required" => false,
	                            "switch"   => false,
	                            "multi"    => false,
	                            "param"    => array(),

	                            // DON'T set any of these by hand!
	                            "set"      => false,
	                            "valid"    => false);

	var	$help_command_name = "";
	var	$help_short_len = 0;
	var	$help_long_len = 0;

	var	$page_width=74;

	function CLI($args = "") {
		if( is_array($args) ) {
			$this->_InitSourceArgsArray($args);
		}

		$this->found_args = array();
		$this->errors = array();
	}


	function Parse($argc, $argv, $args = "") {
		$result = true;

		$this->help_command_name = $argv[0];

		if( is_array($args) ) {
			$this->CLI($args);
		}

		// let's get user args...
		$result = ($result & $this->_GetArgs($argc, $argv));

		// if no errors found, lets check'em...
		$result = ($result & $this->_ValidateArgs());

		return $result;
	}

	// returns BOOL if the $key was specified...
	function IsOptionSet($key) {
		$result = false;

		if( isset($this->args[$key]) ) {
			$result = $this->args[$key]['set'];
		}

		return ($result);
	}


	// returns option argument. Default is '', so it's safe
	// to call this function even against $key is a switch
	// not a regular option
	//
	// NOTE: for arguments with 'multi' == TRUE, you will
	//       get Array back! See the demo...
	function GetOptionArg($key) {
		$result = "";

		if( isset($this->args[$key]) ) {
			if( $this->args[$key]['multi'] ) {
				$result = $this->args[$key]['param'];
			} else {
				$result = $this->args[$key]['param'][0];
			}
		}

		return $result;
	}

	// returns numer of values assigned to the option. Usually
	// it can be 0 (if no option or it's valueless) or X
	function GetOptionArgCount($key) {
		$result = 0;

		if( isset($this->args[$key]) ) {
			if( is_array($this->args[$key]['param']) ) {
				$result = count($this->args[$key]['param']);
			} else {
				$result = 1;
			}
		}

		return $result;
	}

	//outputs errors
	function ShowErrors() {
		$cnt = count($this->errors);

		if( $cnt > 0 ) {
			printf("%d errors found:\n", $cnt);
			foreach( $this->errors AS $error )
				printf("  %s\n", $error);
		} else {
			printf("No errors found\n");
		}
	}

	// produces usge page, based on $args
	function ShowHelpPage() {
		$fmt = sprintf("  %%-%ds %%-%ds ", ($this->help_short_len + 1), ($this->help_long_len + 1));

		$msg = sprintf("\nUsage: %s -opt=val -switch ...\n" . "\nKnown options and switches are detailed below.\n" . " [R] means the option is required,\n" . " [S] stands for valueless switch, otherwise option requires an value,\n" . " [M] means you can use this option as many times, as you need,\n" . "\n", $this->help_command_name);

		foreach( $this->args AS $entry ) {
			$flags = ($entry['required']) ? "R" : "";
			$flags .= ($entry['switch']) ? "S" : "";
			$flags .= ($entry['multi']) ? "M" : "";
			if( $flags != "" )
				$flags = sprintf("[%s] ", $flags);

			$short = ($entry['short'] !== false) ? (sprintf("-%s", $entry['short'])) : "";
			$long = ($entry['long'] !== false) ? (sprintf("-%s", $entry['long'])) : "";

			$tmp = sprintf($fmt, $short, $long);
			$indent = strlen($tmp);
			$offset = $this->page_width - $indent;

			$desc = sprintf("%s%s", $flags, $entry['info']);

			$msg .= sprintf("%s%s\n", $tmp, substr($desc, 0, $offset));

			// does it fit?
			if( strlen($entry['info']) > $offset ) {
				$_fmt = sprintf("%%-%ds%%s\n", $indent);
				$_text = substr($desc, $offset);
				$_lines = explode("\n", wordwrap($_text, $offset));

				foreach( $_lines AS $_line )
					$msg .= sprintf($_fmt, "", trim($_line));
			}
		}

		$msg .= "\n";
		echo $msg;
	}


/***************** PRIVATE FUNCTIONS BELOW ***********************/

	// here we check if source array gave by the application
	// is valid. If any filed is missing, we add it with default
	// value. The only fields that have to be specified are
	// eiher 'short' or 'long' (or both). Do NOT specify 'set'
	// or you will be punished by non-working application ;)
	function _InitSourceArgsArray($args) {
		$this->args = array();

		foreach( $args AS $key => $val ) {
			$tmp = $val;

			foreach( $this->default_fields AS $d_key => $d_val ) {
				if( isset($tmp[$d_key]) === false ) {
					$tmp[$d_key] = $d_val;
				}
			}

			if( ($tmp['short'] === false) && ($tmp['long'] === false) ) {
				// bad, bad developer!
				printf("*** FATAL: Missing 'short' or 'long' definition for '%s'.\n", $key);
				exit(10);
			}

			if( ($tmp['multi'] == true) && ($tmp['switch'] == true) ) {
				printf("*** FATAL: '%s' cannot be both 'switch' and 'multi' argument.\n", $key);
				exit(10);
			}

			// some measures for dynamically layouted help page
			if( $tmp['short'] !== false ) {
				$this->help_short_len = max($this->help_short_len, strlen($tmp['short']));
			}

			if( $tmp['long'] !== false ) {
				$this->help_long_len = max($this->help_long_len, strlen($tmp['long']));
			}

			// arg entry is fine. Load it up
			$this->args[$key] = $tmp;
		}
	}

	// checks if given argumens are known, unique (precheck
	// was made in GetArgs, but we still need to check against
	// non 'multi' arguments given twice
	function _ValidateArgs() {
		$result = true;

		foreach( $this->args AS $key => $entry ) {
			$found = 0;
			$found_as_key = false;

			if( $entry['short'] !== false ) {
				if( array_key_exists($entry['short'], $this->found_args) ) {
					$found++;
					$found_as_key = $entry['short'];
				}
			}

			if( $entry['long'] !== false ) {
				if( array_key_exists($entry['long'], $this->found_args) ) {
					$found++;
					$found_as_key = $entry['long'];
				}
			}

			if( ($entry["multi"] != true) && (count($entry['param']) > 0) ) {
				$this->errors[] = sprintf("Argument '-%s' was already specified.", $entry['long']);
				$result = false;
			}

			// haven't found anything like this yet
			if( $found == 0 ) {
				if( $entry["required"] == true ) {
					$this->errors[] = sprintf("Missing required option '-%s'.", $entry['long']);
					$result = false;
				}
			} else {
				// either short or long keyword was previously found...
				if( $entry["multi"] != true ) {
					if( $found == 2 ) {
						printf("s: %d\n", $entry["multi"]);
						$this->errors[] = sprintf("Argument '-%s' was already specified.", $entry['long']);
						$result = false;
					}
				}

				if( $entry["switch"] === false ) {
					if( $this->found_args[$found_as_key]["val"] === false ) {
						$this->errors[] = sprintf("Argument '-%s' requires value (i.e. -%s=something).", $found_as_key, $found_as_key);
						$result = false;
					}
				} else {
					if( count($this->found_args[$found_as_key]["val"]) == 0 ) {
						printf("'%s' '%s'", $this->found_args[$found_as_key]["val"], $entry["long"]);
						$this->errors[] = sprintf("'-%s' is just a switch, and does not require any value.", $found_as_key);
						$result = false;
					}
				}

				// let's put it back...
				$this->args[$key]['set'] = true;
				if( $entry["switch"] == false ) {
					$this->args[$key]['param'] = $this->found_args[$found_as_key]['val'];
				}

				// remove it from found args...
				unset($this->found_args[$found_as_key]);
			}
		}


		// let's check if we got any unknown args...
		if( count($this->found_args) > 0 ) {
			$msg = "Unknown options found: ";
			$comma = "";
			foreach( $this->found_args AS $key => $val ) {
				$msg .= sprintf("%s-%s", $comma, $key);
				$comma = ", ";
			}

			$this->errors[] = $msg;

			$result = false;
		}

		return $result;
	}

	// scans user input and builds array of given arguments
	function _GetArgs($argc, $argv) {
		$result = true;

		if( $argc >= 1 ) {
			for( $i = 1; $i < $argc; $i++ ) {
				$valid = true;

				$tmp = explode("=", $argv[$i]);

				if( $tmp[0][0] != '-' ) {
					$this->errors[] = sprintf("Syntax error. Arguments start with dash (i.e. -%s).", $tmp[0]);
					$result = false;
				}

				$arg_key = substr(str_replace(array(" "), "", $tmp[0]), 1);

				if( strlen($tmp[0]) <= 0 ) {
					$this->errors[] = sprintf("Bad argument '%s'.", $tmp[0]);
					$valid = $result = false;
				}

				if( $valid ) {
					switch(count($tmp)) {
						case 2:
							$arg_val = $tmp[1];
							break;

						case 1:
							$arg_val = false;
							break;

						default:
							unset($tmp[0]);
							$arg_val = implode("=", $tmp);
							break;
					}

					if( !(isset($this->found_args[$arg_key])) ) {
						$this->found_args[$arg_key] = array("key" => $arg_key,
						                                    "val" => array());
					}
					if( !(in_array($arg_val, $this->found_args[$arg_key]['val'])) ) {
						$this->found_args[$arg_key]['val'][] = $arg_val;
					}
				}
			}
		}

		return $result;
	}

// end of CLI class
}


	// Command Line Options Array		.
	$args = array("source"       => array("short"    => 's',
	                                      "long"     => "src",
	                                      "required" => true,
	                                      "multi"    => true,
	                                      "info"     => 'Source directory (i.e. "data/") which you are going to process and backup.'),

	              "dest"         => array("short" => 'd',
	                                      "long"  => "dest",
	                                      "info"  => 'Destination directory where CD sets will be created. ' .
		                                             'If ommited, your current working directory will be used.'),

	              "media"        => array("long" => "media",
	                                      "info" => "Specifies destination media type to be used. See help-media for details. Default media capacity is 8,5GB (DVD DoubleLayer)."),

	              "mode"         => array("short" => 'm',
	                                      "long"  => 'mode',
	                                      "info"  => 'Specifies working mode. See help-mode for details. Default mode is "test".'),
	              "out-core"     => array("short" => 'c',
	                                      "long"  => 'out-core',
	                                      "info"  => 'Specifies name prefix used for CD sets directories. ' .
		                                             'If not specified, Current date in YYYYMMDD format will be taken.'),
	              "iso-dest"     => array("short" => 't',
	                                      'long'  => 'iso-dest',
	                                      'info'  => 'Specifies target directory PDM should use for storing ISO images ' .
		                                             '(for "iso" and "burn-iso" modes only). If not specified, "dest" value will be used. This option is mostly of no ' .
		                                             'use unless you want ISO images to be stored over NFS. See "Working over NFS" in README'),
	              "split"        => array('long'   => 'split',
	                                      'info'   => 'Enables file splitting (files bigger than media size will be splitted into smaller blocks).',
	                                      'switch' => true),
	              "data-dir"     => array('long' => 'data-dir',
	                                      'info' => 'All backed up data is stored inside of "data-dir" on each set. Defaults is "backup"'),
					  "line-feed"    => array('long'  => 'line-feed',
													  'short' => 'lf',
													  'info' => 'Specifies type of new line codes used in generated text files. Use "help-line-feed" to list all modes. Default is "crlf"'),

	              "pattern"      => array('long' => 'pattern',
	                                      'info' => 'Specifies regular expression pattern for files to be processed. Supports shell "?" and "*" patterns. Needs PHP 4.3.0+'),

	              "help-mode"    => array('long'   => 'help-mode',
	                                      'info'   => 'Shows more information about available work modes.',
	                                      'switch' => true),
	              "help-media"   => array("long"   => "help-media",
	                                      "info"   => "Show media type related help page.",
	                                      "switch" => true),
					 'help-line-feed'=> array('long' => 'help-line-feed',
                									'short' => 'help-lf',
														'info' => 'Shows all supported line feed modes',
														'switch' => true),

	              "help"         => array("short"  => 'h',
	                                      "long"   => "help",
	                                      "info"   => "Shows this help page.",
	                                      "switch" => true));


/******************************************************************************/

	// DON'T touch this! Create ~/.pdm/pdm.ini to override defaults!
	$min_config_version = 21;
	$config_default = array("CONFIG"    => array("version" => 0),
	                        "PDM"       => array("media"                   => 8500,
	                                             "ignore_file"             => ".pdm_ignore",
	                                             "ignore_subdirs"          => true,
	                                             "check_files_readability" => true,
																"line_feed"               => LINE_FEED_MODE_CRLF),
	                        "CDRECORD"  => array("device"    => "1,0,0",
	                                             "fifo_size" => 10),
	                        "GROWISOFS" => array("device" => "/dev/dvd"),
	                        "MKISOFS"   => array(),
	                        "SPLIT"     => array('buffer_size' => 0),);
	$config = array();

	$OUT_CORE = date("Ymd");		// the CD directory prefix (today's date by default)
									// Note: it can't be empty (due to CleanUp() conditions)!

	/** No modifications below this line allowed ******************/

	$KNOWN_MODES = array("test"     => array("write"        => false,
	                                         "mkisofs"      => false,
	                                         "cdrecord"     => false,
	                                         "FileSplit"    => true,
	                                         "RemoveSets"   => false,
	                                         'SeparateDest' => false),
	                     "link"     => array("write"        => true,
	                                         "mkisofs"      => false,
	                                         "cdrecord"     => false,
	                                         "FileSplit"    => true,
	                                         "RemoveSets"   => false,
	                                         'SeparateDest' => false),
	                     "copy"     => array("write"        => true,
	                                         "mkisofs"      => false,
	                                         "cdrecord"     => false,
	                                         "FileSplit"    => false,
	                                         "RemoveSets"   => false,
	                                         'SeparateDest' => false),
	                     "move"     => array("write"        => true,
	                                         "mkisofs"      => false,
	                                         "cdrecord"     => false,
	                                         "FileSplit"    => false,
	                                         "RemoveSets"   => false,
	                                         'SeparateDest' => false),
	                     "iso"      => array("write"        => true,
	                                         "mkisofs"      => true,
	                                         "cdrecord"     => false,
	                                         "FileSplit"    => true,
	                                         "RemoveSets"   => true,
	                                         'SeparateDest' => true),
	                     "burn"     => array("write"        => true,
	                                         "mkisofs"      => true,
	                                         "cdrecord"     => false,
	                                         "FileSplit"    => true,
	                                         "RemoveSets"   => true,
	                                         'SeparateDest' => false,),
	                     "burn-iso" => array("write"        => true,
	                                         "mkisofs"      => true,
	                                         "cdrecord"     => true,
	                                         "FileSplit"    => true,
	                                         "RemoveSets"   => true,
	                                         'SeparateDest' => false));

/******************************************************************************/

	// PHP requirement
	define('PHP_MIN_VERSION', '5.3.0');

	// user name we run under
	define("USER", getenv("USER"));

	// some useful constants
	define("GB",	pow(1024,3));
	define("MB",	pow(1024,2));
	define("KB",	pow(1024,1));

	// line feed modes
	define('LINE_FEED_MODE_LF', 'lf');
	define('LINE_FEED_MODE_CRLF', 'crlf');


	// As for CDs: according to http://www.cdrfaq.org/faq07.html#S7-6
	// each sector is 2352 bytes, but only 2048 bytes can be used for data

	// As for DVDs: http://www.osta.org/technology/dvdqa/dvdqa6.htm
	define("SECTOR_SIZE", 2352);
	define("SECTOR_CAPACITY", 2048);
	define("AVG_BYTES_PER_TOC_ENTRY", 400);     // bytes per file entry in filesystem TOC
												// this is rough average. I don't want AI here if CDs are that cheap now...

	// we reserve some space for internal CD stuff
	define( "RESERVED_CAPACITY", (5*MB) );
	define( "RESERVED_SECTORS"	, round( (RESERVED_CAPACITY/SECTOR_CAPACITY)+0.5), 0 );

	$MEDIA_SPECS = array(184  => array("capacity"      => 184.6 * MB,
	                                   "max_file_size" => (184.6 * MB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 94500 - RESERVED_SECTORS,
	                                   'type'          => 'CD',
	                                   'handler'       => 'cd'),
	                     553  => array("capacity"      => 553.7 * MB,
	                                   "max_file_size" => (553.7 * MB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 283500 - RESERVED_SECTORS,
	                                   'type'          => 'CD',
	                                   'handler'       => 'cd'),
	                     650  => array("capacity"      => 650.3 * MB,
	                                   "max_file_size" => (650.3 * MB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 333000 - RESERVED_SECTORS,
	                                   'type'          => 'CD',
	                                   'handler'       => 'cd'),
	                     700  => array("capacity"      => 703.1 * MB,
	                                   "max_file_size" => (703.1 * MB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 360000 - RESERVED_SECTORS,
	                                   'type'          => 'CD',
	                                   'handler'       => 'cd'),
	                     800  => array("capacity"      => 791.0 * MB,
	                                   "max_file_size" => (791.0 * MB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 405000 - RESERVED_SECTORS,
	                                   'type'          => 'CD',
	                                   'handler'       => 'cd'),
	                     870  => array("capacity"      => 870.1 * MB,
	                                   "max_file_size" => (870.1 * MB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 445500 - RESERVED_SECTORS,
	                                   'type'          => 'CD',
	                                   'handler'       => 'cd'),

	                     // DVD
	                     1460 => array("capacity"      => 1.4 * GB,
	                                   "max_file_size" => (1.4 * GB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 712891 - RESERVED_SECTORS,
	                                   'type'          => 'DVD',
	                                   'handler'       => 'dvd',
	                                   'notes'         => 'DVD-RW/DVD-R 8cm'),
	                     4700 => array("capacity"      => 4.7 * GB,
	                                   "max_file_size" => (4.7 * GB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 2294922 - RESERVED_SECTORS,
	                                   'type'          => 'DVD',
	                                   'handler'       => 'dvd',
	                                   'notes'         => 'DVD-RW/DVD-R 12cm'),
	                     8500 => array("capacity"      => 8.5 * GB,
	                                   "max_file_size" => (8.5 * GB) - (RESERVED_CAPACITY * 2),
	                                   "sectors"       => 4171712 - RESERVED_SECTORS,
	                                   'type'          => 'DVD+R',
	                                   'handler'       => 'dvd',
	                                   'notes'         => 'DVD+R Double Layer'),);


	define("ANSWER_NO"		, 0 );
	define("ANSWER_YES"     , 1 );
	define("ANSWER_ABORT"	, 2 );

function getYN($defaultResponse = false, $prompt = "", $appendKeys = true) {
	if( $defaultResponse ) {
		return (getYes($prompt, $appendKeys));
	} else {
		return (getNo($prompt, $appendKeys));
	}
}

function getNo($prompt = "", $appendKeys = true) {
	$result = ANSWER_ABORT;

	if( $prompt == "" ) {
		$prompt = "Do you want to proceed [N]o/[y]es/[a]bort: ";
	} else if( $appendKeys ) {
		$prompt = $prompt . " [N]o/[y]es/[a]bort]: ";
	}

	while(true) {
		echo $prompt;
		$answer = strtolower(getInput());

		if( $answer == 'y' ) {
			return ANSWER_YES;
		} else if( ($answer == 'n') || ($answer == "") ) {
			return ANSWER_NO;
		} else if( $answer == 'a' ) {
			return ANSWER_ABORT;
		}
	}
}

function getYes($prompt = "", $appendKeys = true) {
	$result = ANSWER_ABORT;

	if( $prompt == "" ) {
		$prompt = "Do you want to proceed [Y]es/[n]o/[a]bort: ";
	} else if( $appendKeys ) {
		$prompt = $prompt . " [Y]es/[n]o/[a]bort: ";
	}

	while(true) {
		echo $prompt;
		$answer = strtolower(getInput());

		if( ($answer == 'y' )  || ($answer == "") ) {
			return ANSWER_YES;
		} else if( ($answer == 'n') ) {
			return ANSWER_NO;
		} else if( $answer == 'a' ) {
			return ANSWER_ABORT;
		}
	}
}

/** @noinspection PhpInconsistentReturnPointsInspection */
function getInput() {
	if( $fh = fopen("php://stdin", "rb") ) {
		$result = chop(fgets($fh, 4096));
		fclose($fh);

		return $result;
	} else {
		echo "\n*** FATAL ERROR: Can't open STDIN for reading!\n";
		Abort();
	}
}

function makeDir($path) {
	if( file_exists($path) === false ) {
		mkdir($path, 0700, true);
	}
}

function SizeStr($fileSize, $precision = -1) {

	if( $fileSize >= GB ) {
		$fileSize = round($fileSize / GB * 100, $precision) / 100 . " GB";
	} else if( $fileSize >= MB ) {
		$fileSize = round($fileSize / MB * 100, $precision) / 100 . " MB";
	} else if( $fileSize >= KB ) {
		$fileSize = round($fileSize / KB * 100, $precision) / 100 . " KB";
	} else {
		$fileSize = $fileSize . " B";
	}

	return $fileSize;
}


// reads configuration pdm.ini file, checks for known items,
// fills missing with default values etc...
function getConfig() {
	global $config_default;

	$result = array("rc"          => false,
	                "config_file" => "none",
	                "config"      => array());

	$locations = array(sprintf("%s/.pdm", getenv("HOME")), "/etc/pdm");
	foreach( $locations AS $path ) {
		$config = sprintf("%s/%s", $path, "pdm.ini");
		if( file_exists($config) ) {
			$result["config_file"] = $config;
			$config_read = parse_ini_file($config, true);

			// lets sync user config with default one... In no value in user
			// config is found we get default one...
			foreach( $config_default AS $section => $group ) {
				foreach( $group AS $key => $val ) {
					if( isset($config_read[$section][$key]) ) {
						$result["config"][$section][$key] = $config_read[$section][$key];
					} else {
						$result["config"][$section][$key] = $config_default[$section][$key];
					}
				}
			}

			$result["rc"] = true;
			break;
		}
	}

	return $result;
}

function showLineFeedModeHelp() {
	global $argv;

	printf('
line-feed - speficy line break code used in generated text files. 

       "lf"       - LF (0xA) code used on Linux/Unix systems
       "crlf"     - CRLF (0xD, 0xA) used on Windows (default)

');
}

function showModeHelp() {
	global $argv;

	printf('
mode - specify method of CD/DVD set creation. Available modes:

       "test"     - is you want to see how many discs you
                    would need to store you data, try this,
       "move"     - moves source files into destination disc
                    set directory,
       "copy"     - copies files into destination disc set
                    directory. Needs as much free disk space
                    as source data takes,
       "link"     - creates symbolic links to source data
                    in destination directory. NOTE: some
                    burning software needs to be explicitly
                    told to follow symlinks, otherwise you burn
                    no data!
       "iso"      - acts as "link" described above, but
                    additionally creates ISO image files for
                    each disc created. Requires ISO creation
                    software and as much free disk space as
                    source takes,
       "burn"     - burns CD/DVD sets on-the-fly,
       "burn-iso" - compiles "iso" and "burn" modes. First
                    it creates full iso image, and then burns.
                    Useful for those who for any reason are
                    unable to enjoy on-the-fly burning
                    offered by "burn" mode. Use this one if
                    your hardware really disallows "burn" mode.

');

}

function showMediaHelp() {
	global $MEDIA_SPECS;

	printf("\nKnown media types are:\n\n");
	printf("  Type | Media | Capacity | Notes\n");
	printf(" ------+-------+----------+-------\n");
	foreach( $MEDIA_SPECS AS $key => $info ) {
		$notes = (isset($info['notes'])) ? $info['notes'] : '';
		printf("  %4d | %-5s | %8s | %s\n", $key, $info['type'], SizeStr($info["capacity"]), $notes);
	}

	printf("\n");
}

function cleanUp($force = false) {
	global $KNOWN_MODES, $COPY_MODE, $total_cds, $OUT_CORE;

	// probably not set yet?
	if( ($total_cds < 1) || ($OUT_CORE == "") ) {
		return;
	}

	if( $KNOWN_MODES[$COPY_MODE]['write'] ) {
		switch($force) {
			case true:
				$do_clean = true;
				break;

			default:
				printf("\nCleaning up temporary data...\n");
				$do_clean = (getYN(true, "  Clean temporary directories? [Y/n]: ", false) == ANSWER_YES);
				break;
		}

		if( $do_clean ) {
			cleanUpRemoveSets($total_cds);
		}
	}
}

function cleanUpRemoveSets($total_cds) {
	global $OUT_CORE, $DESTINATION;

	for( $i = 1; $i <= $total_cds; $i++ ) {
		$src_name = sprintf("%s/%s_%02d", $DESTINATION, $OUT_CORE, $i);

		if( file_exists($src_name) ) {
			$cmd = sprintf("rm -rf %s", $src_name);
			system($cmd);
		}

		printProgressBarRaw(calculateProgressBarPercent($total_cds, $i), " Cleaning");
	}

	printProgressBarRaw(100, " Cleaning");
	printf("\n");
}

function Abort($rc = 10) {
	if( $rc != 0 ) {
		echo "\n*** Cleaning...\n";
	}
	cleanUp(true);

	if( $rc != 0 ) {
		echo "*** Script terminated\n\n";
	}
	exit($rc);
}

function AbortOnErrors($error_cnt) {
	if( $error_cnt > 0 ) {
		printf("\n%d critical error(s) occured. Operation aborted.\n", $error_cnt);
		Abort();
	}
}

function AbortIfNoTool($tool) {
	// check if we can access cdrecord tool
	$cmd = sprintf("which %s", $tool);
	$location = trim(`$cmd`);

	if( file_exists($location) ) {
		if( !(is_executable("$location")) ) {
			printf("FATAL: User '%s' is unable to launch '%s' tool.\n", USER, $tool);
			printf("       Make sure you belong to right group and have privileges\n");
			Abort();
		}
	} else {
		printf("FATAL: Can't find '%s' software. Make sure it's installed\n" . "       and is in your \$PATH. It may be that you got insufficient\n" . "       privileges to use this tool. Check it.\n", $tool);
		Abort();
	}
}

function filematch_fake($pattern, $str) {
	return true;
}

function filematch_fnmatch($pattern, $str) {
	return (fnmatch($pattern, $str));
}


// setting up fake wrapper - using wrapper speeds upi further processing
// as we don't need any comparisons for each call, which would punish
// us whenever number of processing files exceeds 10 ;)
$FILEMATCH_WRAPPER = 'filematch_fake';


function fileSplit($in, $out_array, $chunk_size, $progress_meter = "") {
	global $config;

	$result = false;

	$step = $config['SPLIT']['buffer_size'];
	if( $step == 0 ) {
		$step = min((ini_get('memory_limit') * MB), $chunk_size) / 3;
	}

	if( file_exists($in) ) {
		if( $fh_in = fopen($in, "rb+") ) {
			$file_size = getFileSize($in);
			$parts = ceil($file_size / $chunk_size);

			for( $i = 0; $i < $parts; $i++ ) {
				makeDir($out_array[$i]['path']);

				$write_name = sprintf("%s/%s_%03d", $out_array[$i]['path'], $out_array[$i]['name'], $i + 1);
				if( $fh_out = fopen($write_name, "wb+") ) {
					$read_remain = $chunk_size;
					while($read_remain > 0) {
						$buffer = fread($fh_in, $step);

						fwrite($fh_out, $buffer);

						if( $progress_meter != "" ) {
							printf(str_replace( array("#NAME#", "#SIZE#"),
												array(basename($in), SizeStr($file_size)), $progress_meter));
						}

						unset($buffer);
						$read_remain -= $step;
						$file_size -= $step;
						if( $file_size < 0 ) {
							$file_size = 0;
						}
					}

					fclose($fh_out);
				}
			}

			fclose($fh_in);

			$result = true;

			if( $progress_meter != "" ) {
				printf("\n");
			}
		} else {
			printf("*** Can't open '%s' for read\n", $in);
		}
	} else {
		printf("*** File '%s' not found for splitting\n", $in);
	}

	return ($result);
}

function makePath() {
	$cnt = func_num_args();
	$path = "";

	for( $i = 0; $i < $cnt; $i++ ) {
		$tmp = func_get_arg($i);
		if( $tmp != "" ) {
			if( $path != "" ) {
				$path .= "/";
			}
			$path .= $tmp;
		}
	}

	return preg_replace('#\/+#', "/", $path);
}

function makeEntryPath($entry) {
	return makePath($entry['path'], $entry['name']);
}

function tossFiles(&$src, &$tossed, &$stats) {
	global $MEDIA_SPECS, $MEDIA;

	if( count($stats) == 0 ) {
		CreateSet($stats, 1, $MEDIA_SPECS[$MEDIA]["sectors"]);
	}
	$next_id = count($stats) + 1;

	reset($src);
	while(list($key, $file) = each($src)) {
		$toss_ok = false;

		reset($stats);
		while(list($cd_key, $cd) = each($stats)) {
			if( $file['sectors'] <= ($cd['remaining'] - $cd['sectors_toc']) ) {
				$file['cd'] = $cd_key;
				$tossed[$key] = $file;
				unset($src[$key]);

				$stats[$cd_key]['remaining'] -= $file["sectors"];
				$stats[$cd_key]["files"]++;
				$stats[$cd_key]["bytes"] += $file["size"];
				$stats[$cd_key]["sectors"] += $file["sectors"];
				$stats[$cd_key]["sectors_toc"] = round(((($stats[$cd_key]["files"] * AVG_BYTES_PER_TOC_ENTRY) / SECTOR_CAPACITY) + 0.5), 0);

				$toss_ok = true;
				break;
			}
		}

		if( $toss_ok == false ) {
			$cd_key = $next_id;
			CreateSet($stats, $cd_key, $MEDIA_SPECS[$MEDIA]["sectors"]);

			$file['cd'] = $cd_key;
			$tossed[$key] = $file;
			unset($src[$key]);

			$stats[$cd_key]['remaining'] -= $file["sectors"];
			$stats[$cd_key]["files"]++;
			$stats[$cd_key]["bytes"] += $file["size"];
			$stats[$cd_key]["sectors"] += $file["sectors"];
			$stats[$cd_key]["sectors_toc"] = round(((($stats[$cd_key]["files"] * AVG_BYTES_PER_TOC_ENTRY) / SECTOR_CAPACITY) + 0.5), 0);

			$next_id++;
		}
	}
}

function CreateSet(&$stats, $current_cd, $capacity) {
	$stats[$current_cd]["cd"] = $current_cd;
	$stats[$current_cd]["files"] = 0;
	$stats[$current_cd]["bytes"] = 0;
	$stats[$current_cd]["sectors"] = 0;
	$stats[$current_cd]["sectors_toc"] = 0;
	$stats[$current_cd]['remaining'] = $capacity;
}

/******************************************************************************/

// main() ;-)

	printf(
		"\n" .
		"PHP Backup Maker v%s%s by Marcin Orlowski <carlos@wfmh.org.pl>\n" .
		"----------------------------------------------------------------\n" .
		"Project home: https://bitbucket.org/borszczuk/php-backup-maker/\n"
			, SOFTWARE_VERSION
			, (SOFTWARE_VERSION_BETA) ? SOFTWARE_VERSION_BETA_STR : ""
	);

	if( SOFTWARE_VERSION_BETA ) {
		printf("\n*** This is BETA version. May crash, trash, splash... Be warned!\n");
	}


	if( (version_compare(phpversion(), PHP_MIN_VERSION, "<")) ) {
		printf("This script requires PHP %s or newer. Please update your environment\n", PHP_MIN_VERSION);
		Abort(0);
	}

	$cCLI = new CLI( $args );

	$args_result = $cCLI->Parse( $argc, $argv );

	if( $cCLI->IsOptionSet("help") ) {
		$cCLI->ShowHelpPage();
		Abort(0);
	}

	if( $cCLI->isOptionSet('help-line-feed')) {
		showLineFeedModeHelp();
		Abort(0);
	}

	if( $cCLI->IsOptionSet("help-media") ) {
		showMediaHelp();
		Abort(0);
	}

	if( $cCLI->IsOptionSet("help-mode") ) {
		showModeHelp();
		Abort(0);
	}

	if( $args_result == false ) {
		$cCLI->ShowHelpPage();
		$cCLI->ShowErrors();
		Abort(0);
	}

	// checking how is your PHP configured...
	if( ini_get('safe_mode') != "" ) {
		// Franly it's not fully true. The script would work with
		// safe mode too, but since we wouldn't be able to i.e.
		// increate time limit, nor to read some files or calls
		// external tools in most cases, to avoid 'bug' reports
		// we better claim it's user-fault ;)
		printf("FATAL: You got Safe Mode turned ON in php.ini file\n");
		printf("       You have to turn it OFF to let this script work\n");
		Abort();
	}


	// make sure it works. I don't expect you got CGI php running in safe mode
	// if you do - you will for sure get timeouted due to time-consuming buring
	// process etc. You also should check if memory_limit in your config file
	// (/etc/php4/CGI/php.ini) is high enough. I don't belive default 8MB will
	// do for anything. If PHP aborts while processing your files throwing
	// memory related errors, edit your config and increase it. I usually got
	// 30MB (but even that wasn't enough for 45000 file set).
	set_time_limit(0);

	// reading user config
	$config_array = getConfig();
	if( $config_array["rc"] ) {
		$config = $config_array["config"];
	} else {
		$config = $config_default;
	}

	// some tweaks
	if( USER == "root" ) {
		$config["PDM"]["check_files_readability"] = false;        // makes no sense for root...
	}

	// some 'debug' info...
	printf("Your memory_limit: %s, config: %s\n\n", ini_get('memory_limit'), $config_array["config_file"] );

   // let's check for outdated configs (if there's any)
	if( $config_array["rc"] ) {
		if( $config["CONFIG"]["version"] < $min_config_version ) {
			printf("NOTE: It seems your %s is outdated.\n", $config_array["config_file"]);
			printf("      This PDM version offers bigger configurability.\n");
			printf("      Please check 'pdm.ini.orig' to find out what's new\n\n");
			if( getYN( FALSE ) != ANSWER_YES ) {
				Abort();
			}
		}
	}

	// pattern uses fnmatch() which is PHP 4.3.0+ enabled only
	if( $cCLI->IsOptionSet("pattern") ) {
		$FILEMATCH_WRAPPER = 'filematch_fnmatch';
		$FILEMATCH_DEF_PATTERN = "*";
	}

	// getting user params...
	$COPY_MODE			= ($cCLI->IsOptionSet("mode"))		? $cCLI->GetOptionArg("mode") : "test";
	$source_dir_array	= (array)$cCLI->GetOptionArg("source");
	$DESTINATION		= ($cCLI->IsOptionSet("dest"))  	? $cCLI->GetOptionArg("dest")		: getenv("PWD");
	$ISO_DEST			= ($cCLI->IsOptionSet("iso-dest"))	? $cCLI->GetOptionArg('iso-dest')	: $DESTINATION;
	$MEDIA 				= ($cCLI->IsOptionSet("media"))		? $cCLI->GetOptionArg("media") 		: $config["PDM"]["media"];
	$OUT_CORE			= ($cCLI->IsOptionSet("out-core"))	? $cCLI->GetOptionArg("out-core")	: date("Ymd");
	$DATA_DIR			= ($cCLI->IsOptionSet('data-dir'))	? $cCLI->GetOptionArg('data-dir')	: "backup";
	$LF_MODE				= ($cCLI->isOptionSet('line-feed')) ? $cCLI->GetOptionArg('line-feed')  : $config['PDM']['line_feed'];

	// no defaults here, as in case of no option specified we got filematch_fake() wrapper in use
	if( $cCLI->IsOptionSet("pattern") ) {
		$PATTERN = $cCLI->GetOptionArg("pattern");
	} else {
		$PATTERN = "";
	}

	// line feed code
	$LF_CODE = "\r\n";
	switch( $LF_MODE ) {
		case LINE_FEED_MODE_LF:
			$LF_CODE = "\n";
			break;

		default:
			$LF_MODE = LINE_FEED_MODE_CRLF;		// in case of garbage passed by user
			$LF_CODE = "\r\n";
			break;
	}

	// lets check user input
	if( array_key_exists($COPY_MODE, $KNOWN_MODES) === false ) {
		printf("ERROR: Unknown mode: '%s'\n\n", $COPY_MODE);
		showModeHelp();
		Abort();
	}

	if( array_key_exists($MEDIA, $MEDIA_SPECS) === false ) {
		printf("ERROR: Unknown media type: '%s'\n\n", $MEDIA);
		showMediaHelp();
		Abort();
	}


	// can we split in this mode?
	if( $cCLI->IsOptionSet('split') && ($KNOWN_MODES[$COPY_MODE]['FileSplit'] == false) ) {
		printf("ERROR: Splitting is not available for '%s' mode\n\n", $COPY_MODE);
		Abort();
	}

	// let's check if we don't' have directory names clash (i.e. different "etc" and
	// "/etc" would result in the same destination "etc" directory in the backup
	// for now we complain and abort
	$dest_roots = array();
	foreach( $source_dir_array AS $source_dir ) {
		$dir = makePath($source_dir);        // cleaning up, to avoid "/dir///dir" mess
		$tmp = explode('/', $dir);
		$dir = ($source_dir{0} == '/') ? $tmp[1] : $tmp[0];
		if( in_array($dir, $dest_roots) ) {
			printf("ERROR: Source '%s' directory name clashes with other dir.\n", $source_dir);
			printf("       Example: '/etc/...' and 'etc/...' produces the same 'etc'\n");
			printf("       for backup dir, which is a problem. Change one of these\n");
			printf("       names (even temporary) to avoid this.\n");
			Abort();
		} else {
			$dest_roots[] = $dir;
		}
	}

	// go to dest dir...

	// let's check if source and dest are directories...
	$dirs = array();
	foreach( $source_dir_array as $source_dir ) {
		$dirs[$source_dir] = "r";
	}

	// uf copy mode requires any writting, we need to check if we
	// would be able to write anything to given destdir
	// otherwise we don't care if are write-enabled
	if( $KNOWN_MODES[$COPY_MODE]['write'] == true ) {
		$dirs[$DESTINATION] = "w";

		if( $KNOWN_MODES[$COPY_MODE]['SeparateDest'] == true ) {
			if( $DESTINATION != $ISO_DEST ) {
				$dirs[$ISO_DEST] = "w";
			}
		}
	}

	foreach( $dirs AS $dir=>$opt ) {
		if( !(is_dir($dir)) ) {
			printf("FATAL: '%s' is not a directory or doesn't exists\n", $dir);
			Abort();
		}

		if( strstr($opt, 'w') !== false ) {
			if( !(is_writable($dir)) ) {
				printf("FATAL: user '%s' can't write to '%s' directory.\n", USER, $dir);
				Abort();
			}
		}

		if( strstr($opt, 'r') !== false ) {
			if( !(is_readable($dir)) ) {
				printf("FATAL: user '%s' can't read '%s' direcory.\n", USER, $dir);
				Abort();
			}
		}
	}


	// let's check if we can allow given mode
	if( $KNOWN_MODES[$COPY_MODE]['mkisofs'] ) {
		AbortIfNoTool("mkisofs");
	}

	if( $KNOWN_MODES[$COPY_MODE]['cdrecord'] ) {
		AbortIfNoTool("cdrecord");
	}

	// lets check if there's no sets or ISO images here...
	for($i=1; $i<10; $i++) {
		$name = sprintf("%s/%s_%02d", $DESTINATION, $OUT_CORE, $i);
		if( file_exists($name) ) {
			printf("Found old sets in '%s'.\n", $DESTINATION);
			if( getNo("Shall I remove them and proceed") != ANSWER_YES ) {
				Abort();
			} else {
				cleanUpRemoveSets(99);
				break;
			}
		}

		if( $KNOWN_MODES [$COPY_MODE]['mkisofs'] == "iso" ) {
			if( file_exists(sprintf("%s.iso", $name)) ) {
				printf("FATAL: Found old image '%s.iso'.\n", $name);
				printf("       Remove or rename them first or choose other destination.\n");
				Abort();
			}
		}
	}


	// Go!
	$files = array();

	printf("Scanning. Please wait...\n");
	foreach( $source_dir_array as $source_dir ) {
		$soure_dir = preg_replace('#\/+#', '/', $source_dir);

		printf("  Dir: '%s'... ", $source_dir);

		// lets scan $source_dir and subdirectories and look for files...
		$modes = array('f' => 'regular files',
		               'l' => 'symbolic links');

		$empty_counter = count($modes);
		foreach( $modes AS $scan_mode => $scan_mode_name ) {
			$a = trim(`find $source_dir/ -depth -type $scan_mode -print`);

			if( $a != "" ) {
				$files_tmp = explode("\n", $a);
				$files = array_merge_recursive($files, $files_tmp);
			} else {
				$empty_counter++;
			}
		}

		if( $empty_counter == 0 ) {
			printf("Directory seems to be empty.");
		}

		echo "\n";
	}

	asort($files);

	$target = array();
	$target_split = array();
	$dirs_to_ommit = array();
	clearstatcache();

	$fatal_errors = 0;
	$i=0;


	printf("Processing file list...\n");

	printf("  Gettings file sizes...\n");
	$pattern_skipped_files = 0;
	foreach( $files AS $key=>$val ) {
		$size = getFileSize( $val );

		$dir = dirname( $val );
		$name = basename( $val );

		// is it our special file? if so, we should remember this dir for
		// further processing...
		if( $name == $config["PDM"]["ignore_file"] ) {
			if( isset($dirs_to_ommit[$dir]) == false ) {
				$dirs_to_ommit[$dir] = $dir;
			}
		}

		// if enabled, let's check if user can read this file
		if( $config["PDM"]["check_files_readability"] ) {
			if( !(is_readable($val)) ) {
				printf("  *** User '%s' can't read '%s' file...\n", USER, $val);
				$fatal_errors++;
			}
		}

		// let's check if file matches our pattern finally

		if( $FILEMATCH_WRAPPER( $PATTERN, $val ) ) {
			$file_size = getFileSize( $val );
			$target[$i++] = array(	"name"		=> $name,
									"path"		=> $dir,
									"size"		=> $file_size,
									"split"		=> FALSE,			// do we need to split this file?
									"sectors"	=> round( (($file_size / SECTOR_CAPACITY) + 0.5), 0 ),
									"cd"			=> 0						// #of CD we move this file into
								);
		} else {
			$pattern_skipped_files++;
		}

		unset( $files[ $key ] );
	}
	AbortOnErrors( $fatal_errors );

	if( $pattern_skipped_files > 0 ) {
		printf("    Pattern skipped files: %d\n", $pattern_skipped_files);
	}


	// filtering out dirs we don't want to backup
	if( count( $dirs_to_ommit ) > 0 ) {
		printf("  Filtering out content marked with '%s' in:\n", $config["PDM"]["ignore_file"]);
		foreach( $dirs_to_ommit AS $dir ) {
			$reduced_cnt = 0;
			$reduced_size = 0;

			printf("    %s\n", $dir );

			$dir_len = strlen( $dir );

			foreach( $target AS $key=>$entry ) {
				if( $config["PDM"]["ignore_subdirs"] == false ) {
					$match = ($entry["path"] == $dir);
				} else {
					$match = (substr($entry["path"], 0, $dir_len) == $dir);
				}

				if( $match ) {
					$reduced_cnt++;
					$reduced_size += $entry["size"];
					unset( $target[$key] );
				}
			}
		}

//		printf("  Filtered out %s in %d files\n", SizeStr( $reduced_size ), $reduced_cnt);
	}


	// let's check if file will fit... Prior v2.1 we had this nicely done in one pass
	// with the above preprocessing, but due to skipping feature we need to slow things
	// down at the moment
	printf("  Checking filesize limits (using %s media)...\n", SizeStr( $MEDIA_SPECS[ $MEDIA ]["capacity"] ));

	$files_to_split = array();
	$split_active = $cCLI->IsOptionSet('split');

	// can't use foreach due its 'work-on-copy' issue
	$big_files = 0;
	reset( $target );
	while( list($key, $entry) = each( $target ) ) {
		if( $entry["size"] >= $MEDIA_SPECS[ $MEDIA ]["max_file_size"] ) {
			if( $split_active ) {
				$target[$key]['split'] = TRUE;

				$files_to_split[ $key ] = $target[ $key ];
				$files_to_split[ $key ]['splitted'] = FALSE;
				$files_to_split[ $key ]['parts'] = array();
			} else {
				// we have to give up all the files bigger than the CD capacity
				printf("  *** File \"%s\" is too big (%s)\n", makeEntryPath( $entry ), SizeStr($entry['size']) );
				$fatal_errors++;
				$big_files++;
			}
		}
	}
	if( $big_files > 0 ) {
		printf("\nUse -split feature to handle big files.\nSee README for more information\n");
	}
	AbortOnErrors( $fatal_errors );


	// Let's split what should be splitted. Here we gona fake a bit. We remove big files from the
	// list first and create fake 'splitted' parts instead. Then we toss it and finally we do
	// really split the file. This kind of trickery is somehow required here, to have splitting
	// added without wrecking PDM internals. It could and should be made in clearer way, but since
	// it's not as painful as it looks I keep it instead of rewriting the whole PDM (for now)
	if( count( $files_to_split ) > 0 ) {
		printf("  Splitting files bigger than %s...\n", SizeStr( $MEDIA_SPECS[ $MEDIA ]['max_file_size'] ) );

		$trashcan = array();

		reset( $files_to_split );
		while( list($key, $file) = each( $files_to_split ) ) {
			if( $file['split'] == TRUE ) {
				$chunk_size = $MEDIA_SPECS[ $MEDIA ]['max_file_size'];
				$parts = ceil( $file['size'] / $chunk_size );

				printf("    '%s' (%s) into %d chunks\n", $file['name'], SizeStr( $file['size'] ), $parts );

				$tmp_size = $file['size'];
				for( $i=0; $i<$parts; $i++ ) {
					$tmp = $file;

					$file_size = intval(($tmp_size > $chunk_size) ? $chunk_size : $tmp_size);

					$tmp['size'] = $file_size;
					$tmp['name'] = sprintf("%s_%03d", $file['name'], $i);
					$tmp['sectors'] = round( (($file_size / SECTOR_CAPACITY) + 0.5), 0 );
					$tmp['chunk'] = $i;
					$tmp['source_file_index'] = $key;

					// fake part
					$target_split[] = $tmp;
					$tmp_size -= $chunk_size;

					// big files out
					unset( $target[ $key ] );
					}
				}
			}
		}


	// I know it can be done much smarter, but I hate such 'optimalisation' which
	// kill source readability and clearance. Too many vars in global scope
	// sucks as well (or even more)...
	$total_files_split = 0;
	$total_size_split = 0;
	$total_files_std = 0;
	$total_size_std = 0;

	reset( $target );
	while( list($key, $file) = each( $target ) ) {
		$total_files_std++;
		$total_size_std = bcadd($total_size_std, $file["size"]);
	}
	reset( $target_split );
	while( list($key, $file) = each( $target_split ) ) {
		$total_files_split++;
		$total_size_split = bcadd($total_size_split, $file["size"]);
	}

	$total_files = bcadd($total_files_std, $total_files_split);
	$total_size = bcadd($total_size_std, $total_size_split);

	printf( "\n%d items (%s) found to be processed.\n\n", $total_files, SizeStr($total_size) );
	if( $total_files <= 0 ) {
		Abort();
	}


	// let's go, as long as we got something to process...
	$tossed = array();									// here we going to have tossed files at the end
	$stats = array();										// some brief statistics for each set we create


	// Tossing...
	printf("Tossing...\n");

	tossFiles( $target, $tossed, $stats );
	$tmp_split = $target_split;					// since Toss() unset()'s elements, we use tmp array, as we need target_split later on...
	tossFiles( $tmp_split, $tossed, $stats );

	$total_cds = count($stats);
	printf("%-70s\n", sprintf("Tossed into %d %s%s of %s each...",
															$total_cds, $MEDIA_SPECS[$MEDIA]['type'],
															($total_cds > 1) ? 's' : '',
															SizeStr( $MEDIA_SPECS[$MEDIA]["capacity"] ) ) );


	// tell me what we have done...
	foreach( $stats AS $item ) {
		printf(" %s: %2d, files: %5d, ISO FS: %9.9s + specials\n", $MEDIA_SPECS[$MEDIA]['type'], $item["cd"],
					$item["files"], SizeStr($item["bytes"]), SizeStr( $item["files"] * AVG_BYTES_PER_TOC_ENTRY) );
	}

	printf("\n");


	// if this is 'test' there's nothing to do, so we quit
	if( $COPY_MODE == "test" )
		exit();


	printf("I'm about to create %s sets from your data (mode: '%s')\n", $MEDIA_SPECS[$MEDIA]['type'], $COPY_MODE);
	if( getYN(TRUE) != ANSWER_YES ) {
		Abort();
	}


	// ok, let's move the files into CD sets
	printf("Creating %s sets (mode: %s) in '%s'...\n", $MEDIA_SPECS[$MEDIA]['type'], $COPY_MODE, $DESTINATION);
	$cnt = $total_files;


	// creating dirs...
	for($i=1;$i<=$total_cds;$i++) {
      makeDir( sprintf("%s/%s_%02d/%s", $DESTINATION, $OUT_CORE, $i, $DATA_DIR ) );
	}


	// tossing standard (non splitted files)
	reset( $tossed );
	while( list($key, $file) = each( $tossed ) ) {
		if( $cnt > 2500 ) {
			$base = 1000;
		} else if( $cnt > 1000 ) {
			$base = 400;
		} else if( $cnt > 200 ) {
			$base = 100;
		} else {
			$base = 1;
		}

		if( $file['split'] == false ) {
			if( ($cnt % $base) == 0 ) {
				printProgressBarRaw(calculateProgressBarPercent($total_files, $cnt), "Preparing");
			}

			$src = makePath($file['path'], $file["name"]);
			$dest_dir = sprintf("%s/%s_%02d/%s/%s", $DESTINATION, $OUT_CORE, $file["cd"], $DATA_DIR, $file["path"]);
			$dest = makePath($dest_dir, $file["name"]);

			makeDir($dest_dir);

			switch($COPY_MODE) {
				case "copy":
					copy($src, $dest);
					break;

				case "move":
					rename($src, $dest);
					break;

				case "link":
				case "iso":
				case "burn":
				case "burn-iso":
					$tmp = explode("/", $src);

					$prefix = "";
					// absolute paths are absolute. period
					$_path = realpath($src);
					if( symlink($_path, $dest) == false ) {
						printf("symlink() failed: %s => %s\n", $_path, $dest);
					}
					break;
			}

			$cnt--;
		}
	}

	printProgressBarRaw( 100, "Preparing" );
	printf("\n");

	reset($tossed);
	while(list($key, $file) = each($tossed)) {
		if( $file['split'] ) {
			$src_idx = $file['source_file_index'];

			$src = makePath("%s%s", $file['path'], $files_to_split[$src_idx]["name"]);
			$dest_dir = sprintf("%s/%s_%02d/%s/%s", $DESTINATION, $OUT_CORE, $file["cd"], $DATA_DIR, $file["path"]);

			reset($files_to_split);
			while(list($spl_key, $spl_file) = each($files_to_split)) {
				if( $spl_key == $src_idx ) {
					$files_to_split[$src_idx]['parts'][$file['chunk']] = array('path' => $dest_dir,
					                                                           'name' => $spl_file['name']);

				}
			}
		}
	}

	// real choping...
	reset($files_to_split);
	while(list($key, $file) = each($files_to_split)) {
		$cnt--;

		$src = makeEntryPath($file);
		$progress = sprintf("%3d:  Splitting '#NAME#' (#SIZE# to go)...\r", $cnt);
		fileSplit($src, $file['parts'], $MEDIA_SPECS[$MEDIA]['max_file_size'], $progress);
	}


	// let's write the index file, so it'd be easier to find given file later on
	printf("Building index files...\n");
	if( $KNOWN_MODES[$COPY_MODE]['write'] ) {
		$data_header = sprintf("{$LF_CODE} Created by PDM v%s%s: %s{$LF_CODE}", SOFTWARE_VERSION, (SOFTWARE_VERSION_BETA ? SOFTWARE_VERSION_BETA_STR : ""), SOFTWARE_URL);
		$data_header .= sprintf(" Create date: %s, %s{$LF_CODE}{$LF_CODE}", date("Y.m.d"), date("H:m:s"));

		$cdindex = $data_header;
		$cdindex .= sprintf("%3.3s | %10.10s | %s{$LF_CODE}", $MEDIA_SPECS[$MEDIA]['type'], "Size", "Full path");
		$cdindex .= "----+------------+----------------------------------------------------{$LF_CODE}";
		for( $i = 1; $i <= $total_cds; $i++ ) {
			$tmp = array();
			foreach( $tossed AS $file ) {
				if( $file["cd"] == $i ) {
					$tmp[] = array('size' => SizeStr($file['size']),
										'path' => makePath($file["path"], $file["name"])
									);
				}
			}

			usort($tmp, function($a, $b) {
				if($a['path'] == $b['path'] ) {
					return 0;
				} else {
					return (strcasecmp($a['path'], $b['path']) < 0) ? -1 : 1;
				}
			});
			foreach( $tmp AS $entry ) {
				$cdindex .= sprintf("%3d | %10.10s | %s{$LF_CODE}", $i, $entry['size'], $entry['path']);
			}

			$cdindex .= $LF_CODE;
		}


		// writing index and stamps...
		for( $i = 1; $i <= $total_cds; $i++ ) {
			$set_name = sprintf("%s_%02d", $OUT_CORE, $i);

			$fh = fopen(sprintf("%s/%s/index.txt", $DESTINATION, $set_name), "wb+");
			if( $fh ) {
				fputs($fh, $cdindex);
				fclose($fh);
			} else {
				printf("*** Can't write index to '%s/%s'\n", $DESTINATION, $set_name);
			}

			// CD stamps
			$fh = fopen(sprintf('%s/%s/VOLUME_%03d_OF_%03d', $DESTINATION, $set_name, $i, $total_cds), "wb+");
			if( $fh ) {
				fputs($fh, $data_header);
				fputs($fh, sprintf(" Out Core: %s", $OUT_CORE));
				fclose($fh);
			}
		}
	}


	if( ($COPY_MODE == "iso") || ($COPY_MODE == "burn") || ($COPY_MODE == "burn-iso") ) {
		printf("\nI'm about to process %s sets (mode '%s') in '%s' directory\n", $MEDIA_SPECS[$MEDIA]['type'], $COPY_MODE, $DESTINATION);
		if( getYN(true) != ANSWER_YES ) {
			Abort();
		}

	$repeat_process = false;        // do we want to do all this again?

	do {
		for( $i = 1; $i <= $total_cds; $i++ ) {
			$out_name = sprintf("%s_%s%02d.iso", $OUT_CORE, strtolower($MEDIA_SPECS[$MEDIA]['type']), $i);
			$vol_name = sprintf("%s_%d_of_%d", $OUT_CORE, $i, $total_cds);
			$src_name = sprintf("%s_%02d", $OUT_CORE, $i);

			$MKISOFS_PARAMS = sprintf(" -R -A 'PDM BACKUP CREATOR  https://bitbucket.org/borszczuk/php-backup-maker/' -follow-links -joliet-long -joliet -rock -full-iso9660-filenames " . " -allow-lowercase -allow-multidot -hide-joliet-trans-tbl -iso-level 2 " . " -overburn -V %s -volset %s ", $vol_name, $vol_name);

			switch($COPY_MODE) {
				case "iso": {
					$cmd = sprintf("mkisofs %s -output %s/%s %s/%s 2>&1", $MKISOFS_PARAMS, $ISO_DEST, $out_name, $DESTINATION, $src_name);
					printProgressBar($cmd, sprintf("ISO %s", $out_name));
				}
					break;

				case 'burn-iso':
				case "burn": {
					if( $COPY_MODE == "burn" )
						$_type = "on-the-fly"; else
						$_type = "via ISO image";

					do {
						printf("\nAttemting to burn %s (#%d of %d) %s (choosing 'N' skips burning of this directory).\n", $src_name, $i, $total_cds, $_type);
						switch(getYN(true)) {
							case ANSWER_YES:
								switch($COPY_MODE) {
									case "burn-iso": {
										// making temporary iso image 1st
										$cmd = sprintf("mkisofs %s -output %s %s/%s 2>&1", $MKISOFS_PARAMS, $out_name, $DESTINATION, $src_name);
										printProgressBar($cmd, sprintf("ISO %s", $out_name));

										switch($MEDIA_SPECS[$MEDIA]['handler']) {
											case 'cd':
//													$burn_cmd = sprintf(
												break;

											case 'dvd':
												$burn_cmd = sprintf("growisofs -dvd-compat -Z %s=%s", $config['GROWISOFS']['device'], $out_name);
												break;
										}
									}
									break;

									case 'burn':
									default: {
										// burn baby! burn!
										switch($MEDIA_SPECS[$MEDIA]['handler']) {
											case 'cd':
												$mkisofs = sprintf("mkisofs %s %s/%s", $MKISOFS_PARAMS, $DESTINATION, $src_name);
												$cdrecord = sprintf("cdrecord -fs=%dm -v driveropts=burnfree speed=0 gracetime=2 -eject -dev=%s - ", $config["CDRECORD"]["fifo_size"], $config["CDRECORD"]["device"]);
												$burn_cmd = sprintf("%s | %s", $mkisofs, $cdrecord);
												break;

											case 'dvd':
												///usr/bin/growisofs -Z /dev/hdc -use-the-force-luke=dao -overburn -V wizfon_01 -volset  -A K3B THE CD KREATOR VERSION 0.11.9 (C) 2003 SEBASTIAN TRUEG AND THE K3B TEAM -P  -p K3b - Version 0.11.9 -sysid LINUX -volset-size 1 -volset-seqno 1 -sort /tmp/kde-carlos/k3baTJhza.tmp -R -hide-list /tmp/kde-carlos/k3b9sWkDb.tmp -J -joliet-long -hide-joliet-list /tmp/kde-carlos/k3bOaDqVb.tmp -L -l -allow-lowercase -allow-multidot -hide-joliet-trans-tbl -iso-level 2 -path-list /tmp/kde-carlos/k3b8GFe0a.tmp
												$burn_cmd = sprintf(" growisofs -f -Z %s %s ", $config["GROWISOFS"]["device"], $src_name);
												break;
										}
									}
								}

								// Go!

								printProgressBar($burn_cmd, "Burning");
								printf("\nThe '%s' has been burnt.\n\n", $src_name);

								$burn_again = getYN(false, sprintf("Do you want to burn '%s' again?", $src_name));

								switch($burn_again) {
									case ANSWER_YES:
										break;

									case ANSWER_NO:
										if( file_exists($out_name) )
											unlink($out_name);
										break;

									case ANSWER_ABORT:
										if( file_exists($out_name) )
											unlink($out_name);
										Abort();
										break;
								}
								break;

							case ANSWER_NO:
								printf(" ** Skipped...\n");
								$burn_again = false;
								break;

							case ANSWER_ABORT:
								Abort();
						}
					} while($burn_again);
				}
					break;
			}
		}

		printf("\n\nOperation done.\n");
		switch($COPY_MODE) {
			case "burn":
				$repeat_process = getYN(false, sprintf("\nDo you want to %s all the %d sets again?", $COPY_MODE, $total_cds));
				break;

			default:
				$repeat_process = false;
				break;
		}


	} while($repeat_process == ANSWER_YES);

	if( $repeat_process == ANSWER_ABORT ) {
		Abort();
	}
}

	// cleaning temporary data files...
	if( $KNOWN_MODES[$COPY_MODE]['RemoveSets'] )
		cleanUp();

	printf("\nDone.\n\n");


function printProgressBarRaw($percent, $msg = "Working") {
	$progress_bar = "##################################################";
	printf("%s: [%-50s] %.1f%%\r", $msg, substr($progress_bar, 0, $percent / 2), $percent);
}

function calculateProgressBarPercent($total, $current) {
	return 100.0 - (float)round(100 - (($total - $current) * 100 / $total));
}

function printProgressBar($cmd, $msg) {
	$pattern = '\'(\ [0-9]{1,2})*\.([0-9]{2})*% done, estimate finish (.*)+\'siU';

	$ph = popen($cmd, "r");
	while(!feof($ph)) {
		$buffer = fgets($ph, 256);
		if( preg_match($pattern, $buffer, $match) ) {
			printProgressBarRaw((float)sprintf("%s.%s", $match[1], $match[2]), $msg);
		}
	}
	pclose($ph);

	printProgressBarRaw(100, $msg);
	printf("\n");
}


// filesize wrapper to solve >2GB size issue
function getFileSize($file) {

	static $iswin;

	if( !isset($iswin) ) {
		$iswin = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
	}

	static $exec_works;
	if( !isset($exec_works) ) {
		$exec_works = (function_exists('exec') && !ini_get('safe_mode') && @exec('echo EXEC') == 'EXEC');
	}

	// try a shell command
	if( $exec_works ) {
		$cmd = ($iswin) ? "for %F in (\"$file\") do @echo %~zF" : "stat -c%s \"$file\"";
		@exec($cmd, $output);
		if( is_array($output) && ctype_digit($size = trim(implode("\n", $output))) ) {
			return $size;
		}
	}

	// try the Windows COM interface
	if( $iswin && class_exists("COM") ) {
		try {
			/** @noinspection PhpUndefinedClassInspection */
			$fsobj = new COM('Scripting.FileSystemObject');
			/** @noinspection PhpUndefinedMethodInspection */
			$f = $fsobj->GetFile(realpath($file));
			$size = $f->Size;
		} catch( Exception $e ) {
			$size = null;
		}
		if( ctype_digit($size) ) {
			return $size;
		}
	}

	// if all else fails
	return filesize($file);
}
