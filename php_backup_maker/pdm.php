#!/usr/bin/php4 -q
<?php
/* vim: set tabstop=3 shiftwidth=3: */

// don't remove this. I don't expect you see any warning/error in my code ;-)
error_reporting(E_ALL);

/*
require_once "Getopt_Util.php";

//{{{ Command Line Options Array
		$CLI = array(
							"help"      	=> array("short"  => 'h',
															"long"   => "help",
															"desc"   => "Shows this help page",
															"opt"    => 'n'
															),
							"help-media"	=>	array(
															"long"	=> "help-media",
															"desc"	=> "Show media type related help page"
															"opt"		=> 'n'
															),

							"media"      => array("short" => 'm',
														 "desc"  => "Specifies destination media type to be used. See help-media for details",
														 "opt"   => 'y' ),

				"source"		=> array( "short"	=> 's',
											 "long"	=> "source",
											 "desc"	=> "Specifies source directory (directory you want to process)",
											 "opt"	=> 'y' ),

				"desc"			=> array(	"short"	=> 'd',
													"long"	=> "dest",
													"desc"	=> "Specifies destination directory. If no specified, current work dir is used"

				"version"   => array("short"  => 'v',
											"long"   => "version",
											"desc"   => "show program version",
											"opt"    => 'n' )
							)
					);
*/

// $Id: pdm.php,v 1.9 2003/01/15 00:33:57 carl-os Exp $
//
// Scans $source_dir (and subdirs) and creates set of CD with the content of $source_dir
//
// Author: Marcin Orlowski <carlos@wfmh.org.pl>
//
// Project home: http://pdm.sf.net/
//               http://wfmh.org.pl/~carlos/
//
define( "SOFTWARE_VERSION", "2.2 beta" );

/******************************************************************************/

	// DON'T touch this! Create ~/.pdm/pdm.ini to override defaults!
	$min_config_version = 21;
	$config_default = array(
						"CONFIG"		=> array("version"							=> 0
												  ),
						"PDM"			=> array("media"								=> 700,
													"ignore_file"						=> ".pdm_ignore",
													"ignore_subdirs"					=> TRUE,
													"check_files_readability"		=> TRUE
													),
						"CDRECORD"	=>	array("enabled"			=>	FALSE,
													"device"				=> "1,0,0",
													"fifo_size"			=> 10
													),
						"MKISOFS"	=> array("enabled"			=> FALSE
													)
						);
	$config = array();

	$OUT_CORE = date("Ymd");		// the CD directory prefix (today's date by default)
											// Note: it can't be empty (due to CleanUp() conditions)!

/** No modyfications below this line are definitely required ******************/

	$KNOWN_MODES = array("test","link","copy","move","iso","burn");

/******************************************************************************/

	// user name we run under
	define("USER", getenv("USER"));

	// some useful constans
	define("GB",	1073741824); 	// 1024^3
	define("MB",	   1048576);   // 1024^2
	define("KB",	     10240);   // 1024^1

	// according to http://www.cdrfaq.org/faq07.html#S7-6
	// each sector is 2352 bytes, but only 2048 bytes can be used for data
	define( "SECTOR_SIZE"					, 2352 );
	define( "SECTOR_CAPACITY"				, 2048 );
	define( "AVG_BYTES_PER_TOC_ENTRY"	,  400 );	// bytes per file entry in filesystem TOC
																	// this is rough average. I don't want
																// AI here if CDs are that cheap now...

	// we reserve some space for internal CD stuff
	define( "RESERVED_SECTORS"	, round( ((5*MB)/SECTOR_CAPACITY)+0.5), 0 );

	$MEDIA_SPECS = array(	184	=> array("min"			=> 21,
														"capacity"	=> 184.6 * MB,
														"sectors"	=> 94500 - RESERVED_SECTORS
														),
									553	=>	array("min"			=> 63,
														"capacity"	=> 553.7 * MB,
														"sectors"	=> 283500 - RESERVED_SECTORS
														),
									650	=> array("min"			=> 74,
														"capacity"	=> 650.3 * MB,
														"sectors"	=> 333000 - RESERVED_SECTORS
														),
									700	=> array("min"			=> 80,
														"capacity"	=> 703.1 * MB,
														"sectors"	=> 360000 - RESERVED_SECTORS
														),
									800	=> array("min"			=> 90,
														"capacity"	=> 791.0 * MB,
														"sectors"	=> 405000 - RESERVED_SECTORS
														),
									870	=> array("min"			=> 99,
														"capacity"	=> 870.1 * MB,
														"sectors"	=> 445500 - RESERVED_SECTORS
														)
								);

//{{{ GetYN						.
function GetYN( $default_reponse=FALSE, $prompt="" )
{
	if( $default_reponse )
		return( GetYes( $prompt ) );
	else
		return( GetNo( $prompt ) );
}
//}}}
//{{{   GetNo					.
function GetNo( $prompt="" )
{
	if( $prompt=="" )
		$prompt="Do you want to proceed [y/N]: ";

	while( TRUE )
		{
		echo $prompt;
		$answer = strtolower( GetInput() );

		if( $answer == 'y' )
			return( TRUE );
		if( ($answer == 'n') || ($answer == "") )
			return( FALSE );
		}
}
//}}}
//{{{   GetYes					.
function GetYes( $prompt="" )
{
	if( $prompt == "" )
		$prompt="Do you want to proceed [Y/n]: ";

	while( TRUE )
		{
		echo $prompt;
		$answer = strtolower( GetInput() );

		if( ($answer == 'y') || ($answer == "") )
			return( TRUE );
		if( $answer == 'n')
			return( FALSE );
		}
}
//}}}
//{{{ GetInput					.
function GetInput()
{
	if( $fh = fopen( "php://stdin", "rb" ) )
		{
		$result = chop( fgets( $fh, 4096 ) );
		fclose( $fh );
		}
	else
		{
		echo "\n*** FATAL ERROR: Can't open STDIN for reading!\n";
		Abort();
		}

	return( $result );
}
//}}}
//{{{ MakeDir					.
function MakeDir( $path )
{
	$tmp = explode("/", $path);
	$dir = "/";

	for( $i=0; $i<count($tmp); $i++ )
		{
		if( $tmp[$i] != "" )
			$dir .= sprintf("%s/", $tmp[$i]);

		if( $dir != "" )
			{
			if( file_exists( $dir ) === FALSE )
				mkdir( $dir, 0700 );
			}
		}
}
//}}}
//{{{ SizeStr					.
function SizeStr( $file_size, $precision=-2 )
{
	if( $file_size >= GB )
		$file_size = round($file_size / GB * 100, $precision) / 100 . " GB";
	else
		if( $file_size >= MB )
			$file_size = round($file_size / MB * 100, $precision) / 100 . " MB";
		else
			if( $file_size >= KB )
				$file_size = round($file_size / KB * 100, $precision) / 100 . " KB";
			else
				$file_size = $file_size . " B";

	return( $file_size );
}
//}}}
//{{{ GetConfig				.

function GetConfig()
{
	global $config_default;

	$config_read = array();
	$result = array("rc"				=>	FALSE,
						 "config_file"	=> "none",
						 "config"		=> array()
						);

	$locations = array(	sprintf("%s/.pdm", getenv("HOME")),
								"/etc/pdm" );
	foreach( $locations AS $path )
		{
		$config = sprintf("%s/%s", $path, "pdm.ini" );
		if( file_exists( $config ) )
			{
			$result["config_file"] = $config;
			$config_read = parse_ini_file( $config, TRUE );

			// lets sync user config with default one... In no value in user
			// config is found we get default one...
			foreach( $config_default AS $section=>$group )
				{
				foreach( $group AS $key=>$val )
					{
					if( isset($config_read[$section][$key]) )
						$result["config"][$section][$key] = $config_read[$section][$key];
					else
						$result["config"][$section][$key] = $config_default[$section][$key];
					}
				}

			$result["rc"] = TRUE;
			break;
			}
		}

	return( $result );
}
//}}}
//{{{ ShowHelp					.
function ShowHelp()
{
	global $argv;

	printf("USAGE: %s mode src [dest]\n", $argv[0]);
	printf('
mode - specify method of CD set creation. Available modes:

       "test" - is you want to see how many CDs you
                need to store you data, try this
       "move" - moves source files into destination CD
                set directory
       "copy" - copies files into destination CD set
                directory. Needs as much free disk space
                as source data takes
       "link" - creates symbolic links to source data
                in destination directory. NOTE: some
                CD burning software needs to be ordered
                to follow symlinks, otherwise you burn
                no data!
       "iso"  - acts as "link" described above, but
                additionally creates ISO image files for
                each CD created. Requires mkisofs and
                as much free disk space as source takes
       "burn" - burns CD sets on-the-fly.

src  - source directory (i.e. "data/") which you are going
       to process and backup
dest - destination directory where CD sets will be created.
       If ommited, your current working directory will be
       used
'
);

}
//}}}
//{{[ ShowMediaHelp
function ShowMediaHelp()
{
	global $MEDIA_SPECS;

	printf("Known media types are:\n\n");
	printf("    Type | Capacity | Mins\n");
	printf("   ------+----------+------\n");
	foreach( $MEDIA_SPECS AS $key=>$info )
		printf( "    %4d | %8s | %4d\n", $key, SizeStr($info["capacity"]), $info["min"] );
}
//}}}
//{{{ CleanUp					.
function CleanUp( $force=FALSE )
{
	global $COPY_MODE, $total_cds, $OUT_CORE;

	// probably not set yet?
	if( ($total_cds < 1) || ($OUT_CORE=="") )
		return;

	if( ($COPY_MODE=="iso") || ($COPY_MODE=="burn") )
		{
		switch( $force )
			{
			case TRUE:
				$do_clean = TRUE;
				break;

			default:
				printf("\nCleaning up temporary data...\n");
				$do_clean = GetYN(TRUE, "  Clean temporary directories? [Y/n]: ");
				break;
			}

		if( $do_clean )
			{
			for( $i=1; $i<=$total_cds; $i++ )
				{
				$src_name = sprintf("%s_cd%02d", $OUT_CORE, $i);
				$cmd = sprintf("rm -rf %s", $src_name);

				printf("    Removing '%s'...\n", $src_name);
				system( $cmd );
				}
			}
		}
}
//}}}
//{{{ Abort						.
function Abort( $rc=10 )
{
	echo "\n*** Cleaning...\n";
	CleanUp( TRUE );

	echo "*** Script terminated\n\n";
	exit( $rc );
}
//}}}

function AbortOnErrors( $error_cnt )
{
   if( $error_cnt > 0 )
		{
		printf("\n%d critical error(s) occured. Operation aborted.\n", $error_cnt );
		Abort();
		}
}


function AbortIfNoTool( $tool )
{
	// check if we can access cdrecord tool
	$cmd = sprintf( "which %s", $tool );
	$location = trim( `$cmd` );
						
	if( file_exists( $location ) )
		{
		if( !(is_executable( "$location" ) ))
			{
			printf("FATAL: User '%s' is unable to launch '%s' tool.\n", USER, $tool );
			printf(  "       Make sure you belong to right group and have privileges\n");
			Abort();
			}
		}
	else
		{
		printf("FATAL: Can't find '%s' software. Make sure it's installed\n" . 
		       "       and is in your \$PATH. It may be that you got insufficient\n" . 
				 "       privileges to use this tool. Check it.\n", $tool);
		Abort();
		}
}


/******************************************************************************/


	printf(
		"\n" .
		"PHP Dump Maker v%s by Marcin Orlowski <carlos@wfmh.org.pl>\n" .
		"----------------------------------------------------------------\n" .
		"Visit project home page: http://pdm.sf.net/ for newest releases\n" .
		"DO NOT report bugs by mail. Use bugtracker on project home page!\n" .
		"Visit http://www.amazon.com/o/registry/20QXY0H72WMJK too!\n"
		, SOFTWARE_VERSION
		);


	if( ($argc != 4) && ($argc !=3) )
		{
		ShowHelp();
		Abort();
		}


	// checking how is your PHP configured...
	if( ini_get('safe_mode') != "" )
		{
		// Franly it's not fully true. The script would work with
		// safe mode too, but since we wouldn't be able to i.e.
		// increate time limit, nor to read some files or calls
		// external tools in most cases, to avoid 'bug' reports
		// we better claim it's user-fault ;)
		printf("FATAL: You got Safe Mode turned ON in php.ini file\n");
		printf("       You have to turn it OFF to make this script work\n");
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
	$config_array = GetConfig();
	if( $config_array["rc"] == FALSE )
		{
		printf("\nNo configuration file found! Please create valid pdm.ini first.\n");
		Abort();
		}
	else
		$config = $config_array["config"];

	// some tweaks
	if( USER == "root" )
		$config["PDM"]["check_files_readability"] = FALSE;		// makes no sense for root...


	// some 'debug' info...
	printf("Your memory_limit: %s, config: %s\n\n",
					ini_get('memory_limit'),
					$config_array["config_file"]
			);

   // let's check for outdated configs
	if( $config["CONFIG"]["version"] < $min_config_version )
		{
		printf("NOTE: It seems your %s is outdated.\n", $config_array["config_file"]);
		printf("      This PDM version offers bigger configurability.\n");
		printf("      Please check 'pdm.ini.orig' to find out what's new\n\n");
		if( GetYN( FALSE ) == FALSE )
			Abort();
		}



	// geting user params...
	$COPY_MODE		= $argv[1];
	$source_dir		= eregi_replace( "//+", "/", $argv[2] );
	$DESTINATION	= ($argc == 4) ? $argv[3] : getenv("PWD");

	// go to dest dir...
	chdir( $DESTINATION );


	// let's check if source and dest are directories...
	$dirs = array( $source_dir=>"r", $DESTINATION=>"w" );
	foreach( $dirs AS $dir=>$opt )
		{
		if( !(is_dir( $dir )) )
			{
			printf("FATAL: '%s' is not a directory or doesn't exists\n", $dir);
			Abort();
			}

		if( strstr( $opt, 'w' ) !== FALSE )
			{
			if( !(is_writable( $dir )) )
				{
				printf("FATAL: user '%s' can't write to '%s' directory.\n", USER, $dir );
				Abort();
				}
			}
			
		if( strstr( $opt, 'r' ) !== FALSE )
			{
			if( !(is_readable( $dir )) )
				{
				printf("FATAL: user '%s' can't read '%s' direcory.\n", USER, $dir);
				Abort();
				}
			}
		}


	// lets check user input
	if( array_search( $COPY_MODE, $KNOWN_MODES ) === FALSE )
		{
		printf("ERROR: Unknown mode: '%s'\n\n", $COPY_MODE );
		ShowHelp();
		Abort();
		}

	if( array_key_exists( $config["PDM"]["media"], $MEDIA_SPECS ) === FALSE )
		{
		printf("ERROR: Unknown media type: '%s'\n\n", $config["PDM"]["media"]);
		ShowMediaHelp();
		Abort();
		}


	// let's check if we can allow given mode
	if( $COPY_MODE == "iso" )
		{
		if( $config["MKISOFS"]["enabled"] == FALSE )
			{
			printf("*** Creating of ISO images is disabled at the moment.\n");
			printf("    Enable 'mkisofs' in pdm.ini\n");
			Abort();
			}
		else
			{
			AbortIfNoTool( "mkisofs" );
			}
		}

	if( ($COPY_MODE == "burn") || ($COPY_MODE == "iso") )
		{
		if( $config["CDRECORD"]["enabled"] == FALSE )
			{
			printf("*** CD burning is disabled at the moment.\n");
			printf("    Enable 'cdrecord' and 'mkisofs' in pdm.ini\n");
			Abort();
			}
		else
			{
			AbortIfNoTool( "cdrecord" );
			}
		}

	// lets check if there's no sets or ISO images here...
	for($i=1; $i<10; $i++)
		{
		$name = sprintf("%s/%s_cd%02d", $DESTINATION, $OUT_CORE, $i);
		if( file_exists( $name ) )
			{
			printf("FATAL: Found old sets in '%s'.\n", $DESTINATION);
			printf("       Remove them first or choose other destination.\n");
			Abort();
			}

		if( $COPY_MODE == "iso" )
			{
			if( file_exists( sprintf("%s.iso", $name) ) )
				{
				printf("FATAL: Found old image '%s.iso'.\n", $name);
				printf("       Remove or rename them first or choose other destination.\n");
				Abort();
				}
			}
		}



	// media
	printf("Target media type %d (%s of capacity)\n\n",	$config["PDM"]["media"], SizeStr($MEDIA_SPECS[ $config["PDM"]["media"] ]["capacity"]) );

	


	// Go!
	printf("Scanning '%s'. Please wait...\n", $source_dir);

	// lets scan $source_dir and subdirectories
	// and look for files...
	$a = trim( `find $source_dir/ -depth -type f -print` );

	if( $a != "" )
		{
		$files = explode ("\n", $a);
		asort($files);
		}
	else
		{
		// find gave nothing returned...
		$files = array();
		}

	$target = array();
	$dirs_to_ommit = array();
	clearstatcache();

	$fatal_errors = 0;
	$i=0;


	printf("Processing file list...\n");

	printf("  Gettings file sizes...\n");
	foreach( $files AS $key=>$val )
		{
		$size = filesize( $val );

		$dir = dirname( $val );
		$dir = substr( $dir, strlen( $source_dir ) );
		$name = basename( $val );

		// is it our special file? if so, we should remember this dir for
		// further processing...
		if( $name == $config["PDM"]["ignore_file"] )
			if( isset( $dirs_to_ommit[$dir] ) == FALSE )
				$dirs_to_ommit[$dir] = $dir;

		// if enabled, let's check if user can read this file
		if( $config["PDM"]["check_files_readability"] )
			if( !(is_readable( $val )) )
				{
				printf("  *** User '%s' can't read '%s' file...\n", USER, $val);
				$fatal_errors++;
				}

		$file_size = filesize( $val );
		$target[$i++] = array(	"name"	=> $name,
										"path"	=> $dir,
										"size"	=> $file_size,
										"sectors"=> round( (($file_size / SECTOR_CAPACITY) + 0.5), 0 ),
										"cd"		=> 0						// #of CD we move this file into
									);

		unset( $files[ $key ] );
		}
	AbortOnErrors( $fatal_errors );


	// filtering out dirs we don't want to backup
	if( count( $dirs_to_ommit ) > 0 )
		{
		printf("  Filtering out content marked with '%s' in:\n", $config["PDM"]["ignore_file"]);
		foreach( $dirs_to_ommit AS $dir )
			{
			$reduced_cnt = 0;
			$reduced_size = 0;

			printf("    %s/%s\n", $source_dir, $dir);

			$dir_len = strlen( $dir );

			foreach( $target AS $key=>$entry )
				{
				if( $config["PDM"]["ignore_subdirs"] == FALSE )
					$match = ( $entry["path"] == $dir );
				else
					$match = ( substr($entry["path"], 0, $dir_len) == $dir );

				if( $match )
					{
					$reduced_cnt++;
					$reduced_size += $entry["size"];
					unset( $target[$key] );
					}
				}
			}

		printf("  Filtered out %s in %d files\n", SizeStr( $reduced_size ), $reduced_cnt);
		}


	// let's check if file will fit... Prior v2.1 we had this nicely done in one pass
	// with the above preprocessing, but due to skipping feature we need to slow things
	// down at the moment
	printf("  Checking filesize limits...\n");
	foreach( $target AS $key=>$entry )
		{
		if( $entry["size"] >= $MEDIA_SPECS[ $config["PDM"]["media"] ]["capacity"] )
			{
			// no, no, no. We won't be splitting files at the moment,
			// we have to give up all the files bigger than the CD capacity
			printf("  *** File %s is too big (%d bytes)\n", $val, $size );
			$fatal_errors++;
			}
		}
	AbortOnErrors( $fatal_errors );


	// I know it can be done much smarter, but I hate such 'optimalisation' which
	// kill source readability and clearance. Too many vars in global scope
	// sucks as well (or even more)...
	$total_files = 0;
	$total_size = 0;
	foreach( $target AS $file )
		{
		$total_files++;
		$total_size += $file["size"];
		}

	printf( "\n%d files (%s) remains for further processing.\n\n", $total_files, SizeStr($total_size) );
	if( $total_files <= 0 )
		Abort();



	// let's go, as long as we got something to process...
	$tossed = array();									// here we going to have tossed files at the end
	$stats = array();										// some brief statistics for each set we create

	$cnt = $total_files;
	$current_cd = 1;
	$cd_remaining = $MEDIA_SPECS[ $config["PDM"]["media"] ]["sectors"];			//how sectors of current_cd we got remaining

	while( $cnt > 0 )
		{
		$stats[ $current_cd ]["cd"] = $current_cd;
		$stats[ $current_cd ]["files"] = 0;
		$stats[ $current_cd ]["bytes"] = 0;
		$stats[ $current_cd ]["sectors"] = 0;
		$stats[ $current_cd ]["sectors_toc"] = 0;

		foreach( $target AS $key=>$file )
			{
			if( $file["sectors"] <= ($cd_remaining - $stats[ $current_cd ]["sectors_toc"]) )
				{
				// ok, it fits here...
				$file["cd"] = $current_cd;
				$tossed[] = $file;
				unset( $target[$key] );

				$cd_remaining -= $file["sectors"];
				$stats[ $current_cd ]["files"] ++;
				$stats[ $current_cd ]["bytes"] += $file["size"];
				$stats[ $current_cd ]["sectors"] += $file["sectors"];
				$stats[ $current_cd ]["sectors_toc"] = round( ((($stats[ $current_cd ]["files"] * AVG_BYTES_PER_TOC_ENTRY) / SECTOR_CAPACITY) + 0.5), 0 );

				$cnt--;
				if( $cnt > 1500 )
					$base = 100;
				else
					if( $cnt > 200 )
						$base = 10;
					else
						$base = 1;

				if( ( $cnt % $base ) == 0 )
					printf("  CD: %3d, unprocessed files left: %5d   \r", $current_cd, $cnt);
				}
			}

		// we processed the while list of remaining files. If there're any left,
		// we have to start a new CD set for them
		$current_cd++;
		$cd_remaining = $MEDIA_SPECS[ $config["PDM"]["media"] ]["sectors"];
		}


	$total_cds = $current_cd - 1;


	printf("%-70s\n", sprintf("Tossed into %d CDs of %s each...", $total_cds, SizeStr( $MEDIA_SPECS[$config["PDM"]["media"]]["capacity"] ) ) );


	// tell me what we have done...
	foreach( $stats AS $item )
		printf("  CD: %2d, files: %5d, ISO FS: %5s + special\n", $item["cd"], $item["files"], SizeStr($item["bytes"]), SizeStr( $item["files"] * AVG_BYTES_PER_TOC_ENTRY) );

	printf("\n");


	// if this is 'test' there's nothing to do, so we quit
	if( $COPY_MODE == "test" )
		exit();


	printf("I'm about to create CD sets from your data (mode: '%s')\n", $COPY_MODE);
	if( GetYN(TRUE) == FALSE)
		Abort();


	// ok, let's move the files into CD sets
	printf("Creating CD sets (mode: %s) in '%s'...\n", $COPY_MODE, $DESTINATION);
	$cnt = $total_files;
	foreach( $tossed AS $file )
		{
		if( $cnt > 2500 )
			$base = 1000;
		else
			if( $cnt > 1000 )
				$base = 400;
			else
				if( $cnt > 200 )
					$base = 100;
				else
					$base = 1;

		if( ( $cnt % $base ) == 0 )
			printf("To do: %10d...\r", $cnt);

		$src 	= sprintf("%s%s/%s", $source_dir, $file["path"], $file["name"]);
		$dest_dir = sprintf("%s/%s_cd%02d/%s", $DESTINATION, $OUT_CORE, $file["cd"], $file["path"] );
		$dest	= sprintf("%s/%s", $dest_dir, $file["name"] );

		switch( $COPY_MODE )
			{
			case "test";
				break;

			case "copy":
				MakeDir( $dest_dir );
				copy( $src, $dest );
				break;

			case "move":
				MakeDir( $dest_dir );
				rename( $src, $dest );
				break;

			case "link":
			case "iso":
			case "burn":
				MakeDir( $dest_dir );
				$tmp = explode("/", $src);
				$prefix = "./";

				for($i=1; $i<count($tmp); $i++)
					{
					if( $tmp[$i] != "" )
						$prefix .= "../";
					}
				if( symlink( sprintf("%s/%s", $prefix, $src), $dest ) == FALSE )
					printf("symlink() failed: %s/%s => %s\n", $prefix, $src, $dest);
				break;

			case "test";
				break;
			}

		$cnt--;
		}

	// let's write the index file, so it'd be easier to find given file later on
	printf("Building index files...\n");
	if( $COPY_MODE != "test" )
		{
		$cdindex  = "\n Created by PDM: http://freshmeat.net/projects/pdm\n";
		$cdindex .= sprintf(" Create date: %s, %s\n\n", date("Y.m.d"), date("H:m:s"));
		$cdindex .= sprintf("%3.3s | %s\n", "CD", "Full path");
		$cdindex .= "----+----------------------------------------------------\n";
		for( $i=1; $i<=$total_cds; $i++ )
			{
			$set_name = sprintf("%s_cd%02d", $OUT_CORE, $i);

			$tmp = array();
			foreach( $tossed AS $file )
				{
				if( $file["cd"] == $i )
					$tmp[] = sprintf("%s/%s", $file["path"], $file["name"] );
				}

			asort( $tmp );
			foreach( $tmp AS $entry )
				$cdindex .= sprintf("%3d | %s\n", $i, $entry);

			$cdindex .= "\n";
			}

		printf("Writting index files and CD stamps...\n");
		for($i=1; $i<=$total_cds; $i++ )
			{
			$fh = @fopen( sprintf("%s/%s/index.txt", $DESTINATION, $set_name), "wb+");
			if( $fh )
				{
				fputs( $fh, $cdindex );
				fclose( $fh );
				}
			else
				{
				printf("*** Can't write index to '%s/%s'\n", $DESTINATION, $set_name);
				}

			// CD stamps
			$fh = fopen( sprintf("%s/%s/THIS_IS_CD_%d_OF_%d", $DESTINATION, $set_name, $i, $total_cds), "wb+");
			if( $fh )
				{
				fputs( $fh, sprintf("Out Core: %s", $OUT_CORE) );
				fclose( $fh );
				}
			}
		}


	if( ($COPY_MODE=="iso") || ($COPY_MODE=="burn") )
		{
		printf("\nI'm about to process CD sets (mode '%s') in '%s' directory\n", $COPY_MODE, $DESTINATION);
		if( GetYN(TRUE) == FALSE)
			Abort();


		$repeat_process = FALSE;		// do we want to do all this again?

		do
			{
//			for( $i=1; $i<=$total_cds; $i++ )
printf("\ndebug!!\n");
$i=1;
				{
				$out_name = sprintf("%s_cd%02d.iso", $OUT_CORE, $i);
				$vol_name = sprintf("%s_%d_of_%d", $OUT_CORE, $i, $total_cds);
				$src_name = sprintf("%s_cd%02d", $OUT_CORE, $i);

				$MKISOFS_PARAMS = sprintf("-follow-links -joliet -rock -full-iso9660-filenames -allow-multidot -V %s", $vol_name);

				switch( $COPY_MODE )
					{
					case "iso":
						{
						$cmd = sprintf("mkisofs %s -output %s %s", $MKISOFS_PARAMS, $out_name, $src_name);
						printf("Creating: %s of %s\n", $out_name, $src_name );
						system( $cmd );
						}
						break;

					case "burn":
						{
						do
							{
							printf("\nAttemting to burn %s (#%d CD of %d) on-the-fly (choosing 'N' skips burning of this directory).\n", $src_name, $i, $total_cds);
							if( GetYN(TRUE) )
								{
								$mkisofs  = sprintf("mkisofs %s %s", $MKISOFS_PARAMS, $src_name);
								$cdrecord = sprintf("cdrecord -fs=%dm -v -eject -dev=%s - ", $config["CDRECORD"]["fifo_size"], $config["CDRECORD"]["device"]);
								$burn_cmd = sprintf("%s | %s", $mkisofs, $cdrecord );

//								$code = system( $burn_cmd );
								printf("\nThe '%s' has been burnt.\n\n", $src_name);

								$burn_again = GetYN( FALSE, sprintf("Do you want to burn '%s' again? [y/N]", $src_name) );
								}
							else
								{
								printf(" ** Skipped...\n");
								$burn_again = FALSE;
								}
							}
							while( $burn_again );
						}
						break;
					}
				}


			printf("\n\nOperation done.\n");
			switch( $COPY_MODE )
				{
				case "burn":
					$repeat_process = GetYN(FALSE, sprintf("\nDo you want to %s all the %d sets again [y/N]?", $COPY_MODE, $total_cds) );
					;
					break;

				default:
					$repeat_process = FALSE;
					break;
				}


			}
			while( $repeat_process );

		}

	// cleaning temporary data files...
	CleanUp();

	printf("\nDone.\n\n");

?>
