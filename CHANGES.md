
 PDM - PHP Backup Maker
 --------------------------------------------------
 Written by Marcin Orlowski <carlos@wfmh.org.pl>
 Home page: https://bitbucket.org/borszczuk/php-backup-maker/


 NOTE: However I did my best to make sure each release contain non
       big bugs, they may still lurk inside. Whenever you notice
       PDM to malfunction please report! Thanks.



 Development history:
 ------------------------
 2015.04.27 - v5.1.0 - added ability to use LF or CRLFs in index files
                     - added files sizes to index files
                     - default .pdm.ini is now set to use 8,5GB DVDs
                     - removed all updateChecker related left-overs
                     - ereg_pattern option removed
                     - removed shortcut for "split" argument

 2015.01.08 - v5.0.1 - removed updateChecker
                     - code cleanup
                     
 2013.11.24 - v5.0 - updated to support PHP 5+ (which is now required)

 2004.08.18 - v4.1 - dropped -volnum-size and -volset-seqno due to mkisofs'
                     announement as read on their mailing list:
                     http://lists.berlios.de/pipermail/cdrecord-support/2004-July/003768.html
                   - PDM now interceprs mkisofs', cdrecord's, growisofs'
                     output and draws nice progress bars itself. It
                     also draws progress bars in other places as well
                   - fixed "-dest"-ination bug causing PDM to not handle
                     correctly most of the cases where CD sets were
                     targeted somewhere else than in current work dir
                   - get rid of relative symlinks in CD sets.

 2004.07.30 - v4.0 - added preliminary (and experimental) DVD support.
                     By using growisofs PDM is now capable of burning
                     your data on DVD. As for now, DVD cannot be burnt
                     on-the-fly but with burn-iso mode only. Sorry.
                     I am about to address the remaining issues which
                     prevents on-the-fly burn outs from working here.
                     And please don't forget to report ANY issue you
                     may spot concerning DVD code! See README for new
                     .ini section information
                   - PDM now process symbolic links. As a side effect
                     source directory scan may last a bit longer than
                     in former versions but it should not be noticeable
                     unless processing bunch of nested directories,
                     but from another hand, loosing symlinks may hurt
                     event stronger. Thanks to Jozef Riha for spotting
                     this (RFE #769543)
                   - PDM now additionally uses volset-size and volset-seqno
                     options of mkisofs to mark each disc of given set
                   - fixed and improved broken -u (-update) feature
                   - pdm.ini - all 'enabled' fields are now obsolete
                   - added small progress bar while preparing files ;)

 2003.05.24 - v3.3 - added 'iso-dest' option (for 'iso' mode only) to
                     speed up ISO image creation over NFS. See README
                     for closer details
                   - changed CD structure. From now, all data inside
                     each set is additionaly placed inside "backup"
                     directory. This cleans whole backup and prevents
                     some backuped files whose names equals to files
                     created by PDM against being overwritten. See
                     '-data-dir' option if you want to override "backup"
                     but NOTE: PDM does not check for any conflicts
                     unwisely chosen '-data-dir' may cause. My advice
                     is "hands off" ;)
                   - added multi argument handling (thanks to newer
                     version of CLI class). Now you can specify more
                     than one source dirs at once (duplicates entries
                     are handled once)
                   - fixed the way PDM used to handle absolute source
                     paths
                   - added check-for-new-version feature. Requires
                     internet connection and php.ini/allow_fopen_url
                     enabled

 2003.05.15 - v3.2 - mkisofs and cdrecord are now enabled by default,
                     which let you use full featured PDM just out of
                     a box without need to create pdm.ini.
                   - fixed tosser issue causing some files to be tossed
                     more than once (PDM used to move them to other CD
                     set even they were already assigned). It ususally
                     resulted on backups blown too much and files were
                     spread over 1 more CD than they should be (CDs were
                     less 'packed' with content). Additionally tossing
                     was a bit slower due to this bug. No file loss nor
                     other damages caused.

 2003.04.26 - v3.1 - under some conditions PDM used to duplicate some
                     files in internal structures, which caused i.e.
                     incorrect reports (tossed more files that we have).
                     It was harmless bug, and neither a file was lost
                     nor duplicated. It was just visual oddity
                   - target temporary directories wasn't created as they
                     should, wrecking usability of PDM completely for
                     some action modes

 2003.04.13 - v3.0 - reduced memory usage by replacing most foreach()
                     loops by while( list(), each() ) equivalents which
                     does not clone arrays
                   - PDM used to generate incomplete index files in
                     say counter-progressive manner. Index on CD 1 kept
                     index just of CD #1, on 2nd, kept index of 1 and 2
                     and on n-th kept it finally competed while it should
                     put the same index file on each CD. Seems nobody
                     except me yet had to find something on multivolume
                     backup yet ;)
                   - fixed argc/argv issue on register_globals disabled
                     environment
                   - implemented -split feature. See README for closer
                     details how to backup your big stuff feature
                     request #702555 by Thien (and lot of others ;)
                   - now correctly reports name and size of file exceeding
                     allowed media capacity
                   - rewrote file tosser routine. It's now significantly
                     faster than old one (tossing 80000 files took pdm3
                     15 seconds while pdm2 needed 27 secs).

 2003.03.12 - v2.5 - cdrecord is now called with additional options:
                     speed=0 (max available), burnfree=On, gracetime=2
                     (burning starts 6 seconds faster (default grace is 8))
                   - removed forgotten debug code in index creation part
                     (not a harmful thingie anyway)
                   - added "burn-iso" mode
                   - PDM used to remove CD sets directiories no matter of
                     used mode. Since these sets are in fact final results
                     of 'link', 'move' and 'copy' modes it could hurt you
                     badly (in 'move' mode especialy').
                   - added 'pattern' option to let you filter out files
                     you process with aid of regexp patterns. This option
                     is only available if you run at least PHP 4.3.0
                     (feature request #698839 by Marcin Juszkiewicz)
                   - added 'ereg-pattern' support for those who needs
                     functionality close to 'pattern' but don't want
                     to upgrade just for this. Please note that 'pattern'
                     and 'ereg-pattern' may differ in pattern syntax

 2003.02.11 - v2.4 - fixed incorrect KB definition (was 10 times too big).
                     Fortunately there's no inpact as this was mostly
                     never used. Reported by J�rg Schwiemann
                   - PDM no longer checks if destination is writable if
                     it's not going to write anything (mostly in -mode=test)
                   - "mode" switch is now optional. When not specified, PDM
                     falls back to "test" as default mode
                   - PDM used to write index files and THIS_IS_CD_x_OF_y
                     markers to the last set only, while it should for each
                     of them
                   - added "out-core" (optional) argument to override CD set
                     name prefix with custom one. If not specified, current
                     date in YYYYMMDD format will be used as usual

 2003.01.23 - v2.3 - removed debug code that <ough> went to public
                     2.2 release making it rather useless unless one
                     manally had fixed the code. Sorry for the inconvenience.

 2003.01.19 - v2.2 - PDM now knows more about CD structure handling. You
                     should specify 'capacity' and 'reserved' any longer
                     ('media' type replaces these) - PDM internal computation
                     is now sector-based, which reduces risk of overburning
                     your CD by 99% and should produce valid image for any
                     number of files in source data set, which was previously
                     complicated sometimes and required to play with RESERVED
                     value manually. However current implementation ain't
                     perfect (yet), and might not fill each set as tightly
                     as you may wish, but frankly I don't care that much when
                     CDs are that cheap. I think it's better to waste 5 MB
                     than screw the whole CD due to overburnin problems etc
                     (it does not mean I'm not open for patches).
                   - added comand line interface handling (with use of
                     class_cli.php). You are now able to overwrite default
                     settings by specifing shell arguments. Please try
                     "pdm -help" for details.
                   - now checks if given source and destination directories
                     are in fact directories, if source is readable and
                     destination writable to the user
                   - added optional (see check_files_readability in .ini)
                     file checking, to ensure each processed file is really
                     readable to user. This shields you from being kicked by
                     mkisofs (i.e. during burn of 5th CD), which aborts if
                     unable to read any file. This option is not active if
                     you launch PDM as root (as it simply make no sense then)
                   - PDM now ensures you have mkisofs and cdrecord installed
                     and available, when ordered to use them
                   - new ini section MKISOFS to turn mkisofs support and then
                     configure this tool
                   - when in 'iso' mode, checks for older ISO images in
                     destination folder to avoid accidental overwrites
                   - pdm.ini is no longer obligatory. It's now optional. If
                     no pdm.ini found, PDM falls back to defaults.

 2003.01.15 - v2.1 - added support for ignore marker. Ignore marker is a file
                     (by default '.pdm_ignore' but its name can be configured
                     via ini file) that tells PDM to skip the directory it
                     resides in from being processed.
                   - changed default behaviour: when no dest dir is specified
                     your current working directory (the dir your are 'in'
                     while launching PDM) will be used instead of script
                     working directory (the dir it resides in).
                   - prior this version PDM had serious problems creating
                     some files and links if dest dir was specified
                   - Y/N 'requesters' can now accept 'Y', 'N' or empty answer
                     (equal to default answer) only. Feeding with crap repeats
                     the question unless valid answer is given.
                   - added ability to repeat whole burning (when in 'burn'
                     mode) again and again. This gives you a possibility to
                     make more than one copy of produced sets or to burn
                     selected set (i.e. when you skipped it, aborted by
                     mistake)
                   - PDM does no startup check against old set directories
                     to avoid potential problems with file/dir name collisions
                   - added .ini versioning, to be able to detect and inform
                     about ancient config files and (probably) misconfigured
                     features

 2003.01.13 - v2.0 - added support for configuration file (pdm.ini). Script
                     first tries to read ~/.pdm/pdm.ini then, if not found,
                     tries /etc/pdm/pdm.ini
                   - renamed the script - it's now just pdm.php to match SF
                     and FM project name (and to avoid confusion)
                   - added more end-user-readable output for all filesizes.
                     Instead of just pure bytecounts like "219942106" you will
                     also face "210MB"
                   - checks PHP config agains safe_mode to avoid newbie
                     problems they may face when running with active SafeMode.

 2003.01.01 - v1.6 - code spead-up ('progress' is repored less often, which
                     speeds up data processing *significantly*
                   - script used to leave temporary CD sets if ordered to Abort
                     after the sets had been created, but before processed
                   - when in burn mode, it no longer asks to burn the CD 'again'
                     if you wished not to burn the CD at all
                   - added config var to control FIFO size (important for all
                     on-the-fly burners)
                   - wrote some kind of documentation (check README ;-)
                   - modified configuration vars to be more self explanatory

 2002.12.10 - v1.5 - seems mkdir() needs now two parameters.

 2002.11.09 - v1.4 - changed the "Cleanup temp data question?",
                   - cleaned source code a bit, to better fit in 80 columns.

 2002.10.20 - v1.3 - added ability of re-burning given CD set (useful if you
                     i.e. procceed too fast, and cdrecord didn't burn anything
                     due to i.e. no blank cd found etc),
                   - reworked the 'UI' to be more friendly, and made most
                     'requesters' to give 'positive' answer as default option,
                     which lets you process your data just with [ENTER] key
                     for most the time...

 2002.08.13 - v1.2 - added "iso" copy mode, which acs like "link" but
                     additionaly creates ISO images (requires mkisofs),
                   - added "burn" copy mode which burns cd set on-the-fly
                     (requires mkisofs and cdrecord). See $CD_DEVICE below!

 2002.07.31 - v1.1 - instead of moving files, the script now symlinks them
                     by default (modify "COPY_MODE" to change this).

 2002.07.30 - v1.0 - initial release.
