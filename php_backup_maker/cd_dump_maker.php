#!/usr/bin/php4 -q
<?php

// cd_dump_maker.php
//
// Scans $source_dir (and subdirs) and creates set of CD with the content of $source_dir
//
// Author: Marcin Orlowski <carlos@wfmh.org.pl>
//
// Project home: http://pdm.sf.net/
//               http://wfmh.org.pl/~carlos/
//
define( "SOFTWARE_VERSION", "1.6" );

// for source formatted with tab spacing = 3

// don't remove this. I don't expect you see any warning/error in my code ;-)
error_reporting(E_ALL);

// make sure it works. I don't expect you got CGI php running in safe mode
// if you do - you will for sure get timeouted due to time-consuming buring
// process etc. You also should check if memory_limit in your config file
// (/etc/php4/CGI/php.ini) is high enough. I don't belive default 8MB will
// do for anything. If PHP aborts while processing your files throwing
// memory related errors, edit your config and increase it. I usually got
// 30MB (but even that wasn't enough for 45000 file set).
set_time_limit(0);

/******************************************************************************/

// SETUP - Adjust to fit your needs. At least, set CD_DEVICE correctly, as other
//         values can be used as-is without any harm

	$CD_DEVICE = "1,0,0";         // use "cdrecord -scanbus" to find out your 
											// CD_DEVICE settings and put the device it 
											// reported (i.e. for 1st CD writer
											// it will be something like "0,0,0")
											// NOTE: don't put any /dev/xxxx here. 
											// CD-Record needs what's above.

	$CD_CAPACITY  = 700;				// you use 700 MB CDRs
	$RESERVED     =   4;				// 4 MB reserved for internal CD structures.
											// NOTE: read README for more details or you
											// may waste some CDRs!

/** No modyfications below are probably required ******************************/

	$FIFO_SIZE = 10;					// how many MB per fifo size (important when burning
											// on the fly. If you got many small files to burn,
											// you may consider increasing this value if RAM
											// permits, however default 10MB should be fine for
											// most uses
	$out_core = date("Ymd");		// the CD directory prefix (today's date by default)
											// Note: it can't be empty (due to CleanUp() conditions)!

/** No modyfications below this line are definitely required ******************/

	$TOTAL_PER_CD = (($CD_CAPACITY - $RESERVED) *1024*1024);

	$KNOWN_MODES = array("test","link","copy","move","iso","burn");

/******************************************************************************/

function GetYN( $default_reponse=FALSE, $prompt="" )
{
	if( $default_reponse )
		return( GetYes( $prompt ) );
	else
		return( GetNo( $prompt ) );
}

function GetNo( $prompt="" )
{
	if( $prompt=="" )
		$prompt="Do you want to proceed [y/N]: ";
	echo $prompt;
	return( (strtolower(GetInput()) == 'y' ) ? TRUE : FALSE );
}
function GetYes( $prompt="" )
{
	if( $prompt == "" )
		$prompt="Do you want to proceed [Y/n]: ";
	echo $prompt;
	return( (strtolower(GetInput()) == 'n' ) ? FALSE : TRUE );
}
	       

function Abort()
{
	echo "\n*** Cleaning...\n";
	CleanUp( TRUE );
	
	echo "\n*** Script terminated\n\n";
	exit(10);
}
					 
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
																																																								

function MakeDir( $path )
{
//	printf("MakeDir path: '%s'\n", $path);

	$tmp = explode("/", $path);
	$dir = "";
	for( $i=0; $i<count($tmp); $i++ )
		{
		if( $tmp[$i] != "" )
			$dir .= sprintf("%s/", $tmp[$i]);
					
		if( $dir != "" )
			{
//			printf("  SubDir: '%s'\n", $dir);
			if( file_exists( $dir ) === FALSE )
				mkdir( $dir, 0700 );
			}
		}
}

function ShowHelp()
{
	global $argv;
	
	printf("USAGE: %s mode src [dest]\n", $argv[0]);
	printf('
mode - specify method of CD set creation. Available:
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
       If ommited, current directory will be used (".")
'
);

}

/******************************************************************************/


printf(	"cd_dump_make.php v%s by Marcin Orlowski <carlos@wfmh.org.pl>\n\n" .
	"Visit project home page: http://pdm.sf.net/ for newest release\n" .
	"Please DO NOT report bugs by mail. Use bugtracker on the sf.net!\n\n"
	, SOFTWARE_VERSION
	);

if( $CD_DEVICE == "" )
	{
	printf("CD_DEVICE is NOT configure. Please edit this script first!\n");
	Abort();
	}

if(  ($argc != 4) && ($argc !=3) )
	{
	ShowHelp();
	Abort();
	}

$COPY_MODE		= $argv[1];
$source_dir		= $argv[2];
$DESTINATION		= ($argc == 4) ? $argv[3] : ".";

if( array_search( $COPY_MODE, $KNOWN_MODES ) === FALSE )
	{
	printf("ERROR: Unknown mode: '%s'\n\n", $COPY_MODE );
	ShowHelp();
	Abort();
	}

printf("Scanning '%s'...\n", $source_dir);

	// lets scan $source_dir and subdirectories 
	// and look for files...
	$a = `find $source_dir -depth -type f -print`;
	$files = explode ("\n", trim($a));
	asort($files);

	$target = array();
	clearstatcache();

	$fatal_errors = 0;
	$i=0;

printf("Processing file list...\n");
foreach( $files AS $key=>$val )
{
	$size = filesize( $val );
	if( $size >= $TOTAL_PER_CD )
		{
		// no, no, no. We won't be splitting files at the moment,
		// we have to give up all the files bigger than the CD capacity
		printf("  *** File %s is too big (%d bytes)\n", $val, $size );
		$fatal_errors++;
		}

	$dir = dirname( $val );
	$dir = substr( $dir, strlen( $source_dir ) );
	$target[$i++] = array("name"	=> basename( $val ),
								"path"	=> $dir,
								"size"	=> filesize( $val ),
								"cd"		=> 0						// #of CD we move this file into
							);

	unset( $files[ $key ] );
}

if( $fatal_errors > 0 )	
	{
	printf("%d critical errors occured. Operation aborted.\n", $fatal_errors );
	Abort();
	}


$current_cd = 1;
$cd_remaining = $TOTAL_PER_CD;						//how many bytes of current_cd we got remaining 

$cnt = count($target);
printf("Tossing %d files...\n", $cnt);

$tossed = array();									// here we going to have tossed files at the end
$stats = array();										// some brief statistics for each set we create

// let's go, as long as we got something to process...
while( $cnt > 0 )
	{
	$stats[ $current_cd ]["cd"] = $current_cd;
	$stats[ $current_cd ]["files"] = 0;
	$stats[ $current_cd ]["bytes"] = 0;
	
	foreach( $target AS $key=>$file )
		{
		if( $file["size"] <= $cd_remaining )
			{
			// ok, it fits here...
			$file["cd"] = $current_cd;
			$tossed[] = $file;
			unset( $target[$key] );

			$cd_remaining -= $file["size"];
			$stats[ $current_cd ]["files"] ++;
			$stats[ $current_cd ]["bytes"] += $file["size"];

			$cnt--;
			if( $cnt > 1500 )
				$base = 100;
			else
				if( $cnt > 200 )
					$base = 10;
				else
					$base = 1;

			if( ( $cnt % $base ) == 0 )
				printf("CD: %3d, unprocessed files left: %5d\r", $current_cd, $cnt);
			}
		}

	// we processed the while list of remaining files. If there're any left,
	// we have to start a new CD set for them
	$current_cd++;
	$cd_remaining = $TOTAL_PER_CD;
	}


$total_cds = $current_cd - 1;

printf("\n");
printf("Tossed into %d CDs...\n", $total_cds );

// tell me what we have done...
foreach( $stats AS $item )
	printf("CD: %3d, files: %6d, bytes in total: %10d\n", $item["cd"], $item["files"], $item["bytes"]);

printf("\n");


if( $COPY_MODE == "test" )
	exit();

	

printf("I'm about to create CD sets from your data (mode: '%s') in '%s' directory\n", $COPY_MODE, $DESTINATION);
if( GetYN(TRUE) == FALSE)
	Abort();

// ok, let's move the files into CD sets
printf("Creating CD sets (mode: %s)...\n", $COPY_MODE);
$cnt = count($tossed);
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
	$dest_dir = sprintf("%s/%s_cd%02d/%s", $DESTINATION, $out_core, $file["cd"], $file["path"] );
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
	$cdindex  = sprintf("\n Create date: %s, %s\n\n", date("Y.m.d"), date("H:m:s"));
	$cdindex .= sprintf("%3.3s | %s\n", "CD", "Full path");
	$cdindex .= "----+----------------------------------------------------\n";
	for( $i=1; $i<=$total_cds; $i++ )
		{
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
		$fh = fopen( sprintf("%s_cd%02d/index.txt", $out_core, $i), "wb+");
		if( $fh )
			{
			fputs( $fh, $cdindex );
			fclose( $fh );
			}

		// CD stamps
		$fh = fopen( sprintf("%s_cd%02d/THIS_IS_CD_%d_OF_%d", $out_core, $i, $i, $total_cds), "wb+");
		if( $fh )
			fclose( $fh );
		}
	}


if( ($COPY_MODE=="iso") || ($COPY_MODE=="burn") )
	{
	printf("\nI'm about to process CD sets (mode '%s') in '%s' directory\n", $COPY_MODE, $DESTINATION);
	if( GetYN(TRUE) == FALSE)
		Abort();

	for( $i=1; $i<=$total_cds; $i++ )
		{
		$out_name = sprintf("%s_cd%02d.iso", $out_core, $i);
		$vol_name = sprintf("%s_%d_of_%d", $out_core, $i, $total_cds);
		$src_name = sprintf("%s_cd%02d", $out_core, $i);
		
		switch( $COPY_MODE )
			{
			case "iso":
				{
				$cmd = sprintf("mkisofs -follow-links -joliet -rock -full-iso9660-filenames -allow-multidot -V %s -output %s %s",
									$vol_name, $out_name, $src_name);
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
						$mkisofs  = sprintf("mkisofs -follow-links -joliet -rock -full-iso9660-filenames -allow-multidot -V %s %s", $vol_name, $src_name);
						$cdrecord = sprintf("cdrecord -fs=%dm -v -eject -dev=%s - ", $FIFO_SIZE, $CD_DEVICE);
						$burn_cmd = sprintf("%s | %s", $mkisofs, $cdrecord );

						$code = system( $burn_cmd );
					printf("RC: %d\n", $code);
						printf("\nThe \"%s\" has been burnt.\n", $src_name);

						$burn_again = GetYN( FALSE, sprintf("Do you want to burn '%s' again? [y/N]", $src_name) );
						}
					else
						{
						printf("    Skipped...\n");
						$burn_again = FALSE;
						}
					}
					while( $burn_again );
				}
				break;
				
			}
		}
	}

// cleaning temporary data files...
	CleanUp();

// cleaning up...
function CleanUp( $force=FALSE )
{
	global $COPY_MODE, $total_cds, $out_core;

	// probably not set yet?
	if( ($total_cds < 1) || ($out_core=="") )
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
				$do_clean = GetYN(TRUE, "Clean temporary directories? [Y/n]: ");
				break;
			}

		if( $do_clean )
			{
			for( $i=1; $i<=$total_cds; $i++ )
				{
				$src_name = sprintf("%s_cd%02d", $out_core, $i);
				$cmd = sprintf("rm -rf %s", $src_name);

				printf(" Removing '%s'...\n", $src_name);
				system( $cmd );
				}
			}
		}
}


printf("\nDone.\n\n");
?>
