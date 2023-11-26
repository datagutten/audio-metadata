<?Php

namespace datagutten\AudioMetadata;

use datagutten\tools\files\files;
use dependcheck;
use DependencyFailedException;
use Exception;
use FileNotFoundException;
use InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AudioMetadata
{
	public $fields=array('title','artist','album','tracknumber','totaltracks','compilation'); //albumartist
    /**
     * Check if metaflac and/or AtomicParsley are installed
     * @param string $extension Specify extension check if the tool for that file type is installed
     * @throws DependencyFailedException
     */
	public static function check_dependencies($extension = null)
    {
        $dependcheck = new dependcheck;
        if(empty($extension) || $extension==='flac')
            $dependcheck->depend('metaflac');
        if(empty($extension) || $extension==='m4a' || $extension==='mp4')
            $dependcheck->depend('AtomicParsley');
    }
	
    /**
     * Build file name for a track
     * @param array $trackinfo Track info
     * @param string $extension File extension
     * @return string File mame
     */
	public static function build_file_name($trackinfo,$extension=null)
	{
		if(isset($trackinfo['compilation']) && $trackinfo['compilation']===true) //Artist skal bare være med i filnavn hvis det er et samlealbum
			$trackname=sprintf('%s - %s',$trackinfo['artist'],$trackinfo['title']);
		else
			$trackname=$trackinfo['title'];
		if(!empty($trackinfo['tracknumber']))
			$trackname=sprintf("%02d %s",$trackinfo['tracknumber'],$trackname);
		if(!empty($trackinfo['totalvolumes']) && $trackinfo['totalvolumes']>1) //Multi volume album
			$trackname=sprintf("%02d %s",$trackinfo['volumenumber'],$trackname);
		if(!empty($extension))
			$trackname.='.'.$extension;
		return filnavn($trackname);
	}

    /**
     * Build directory name for a track
     * @param array $track_info Array with track info
     * @param string $extension File extension to use in folder name
     * @return string Directory name
     */
	public static function build_directory_name($track_info, $extension=null)
	{
		if(!empty($track_info['albumartist']))
			$album_name=filnavn(sprintf('%s - %s',$track_info['albumartist'],$track_info['album']));
		else
			$album_name=filnavn($track_info['album']); //No album artist

		if(!empty($track_info['albumyear']))
			$album_name=sprintf('%s (%d)',$album_name,$track_info['albumyear']);
		elseif(!empty($track_info['year']))
			$album_name=sprintf('%s (%d)',$album_name,$track_info['year']);
		if(!empty($extension))
			$album_name.=' '.strtoupper($extension);
		return $album_name;
	}

    /**
     * Write metadata to a file and move it to the correct path
     * @param $infile
     * @param $outpath
     * @param $trackinfo
     * @return string Renamed file
     * @throws Exception
     */
	public static function metadata($infile,$outpath,$trackinfo)
	{
		if(!file_exists($infile) || !is_file($infile))
		{
			throw new FileNotFoundException($infile);
		}
		$extension=pathinfo($infile,PATHINFO_EXTENSION);
		$filename=self::build_file_name($trackinfo,$extension);
		$album_dir=$outpath.'/'.self::build_directory_name($trackinfo,$extension);
		$output_file=$album_dir.'/'.$filename;

		if(!file_exists($album_dir))
			mkdir($album_dir,0777,true);

		if(empty($trackinfo['cover']))
			$artwork_file=false;
		else
		{
			$artwork_extension=pathinfo($trackinfo['cover'],PATHINFO_EXTENSION);
            $artwork_file = files::path_join($album_dir, filnavn($trackinfo['album']) . '.' . $artwork_extension);
			if(!file_exists($artwork_file))
				copy($trackinfo['cover'],$artwork_file);
		}

		if(file_exists($output_file))
			return $output_file;

		return self::write_metadata($infile, $output_file, $trackinfo, $artwork_file);
	}

    /**
     * Write tags to flac files using metaflac
     * @param string $infile File to be renamed
     * @param string $outfile Where to save the renamed file
     * @param array $trackinfo Track info
     * @param string $artwork Artwork file to embed
     * @param bool $remove_existing Remove existing metadata
     * @return string Renamed file
     * @throws Exception Failed to write metadata
     */
	public static function metaflac($infile,$outfile,$trackinfo,$artwork=null, $remove_existing = true)
	{
	    if(!file_exists($infile))
	        throw new FileNotFoundException($infile);

		@copy($infile,$outfile);
		if(!file_exists($outfile))
            throw new Exception('Unable to create output file');

		$options=array(
				'artist'=>		'ARTIST',
				'title'=>		'TITLE',
				'album'=>		'ALBUM',
				'tracknumber'=>	'TRACKNUMBER',
				'totaltracks'=>	'TRACKTOTAL',
				'volumenumber'=>'DISCNUMBER',
				'compilation'=>	'COMPILATION',
				'isrc'=>		'ISRC',
				'year'=>		'YEAR',
				'copyright'=>	'COPYRIGHT');
		if(isset($trackinfo['compilation']))
		{
			if($trackinfo['compilation']===true)
				$trackinfo['compilation']='1';
			elseif($trackinfo['compilation']===false)
				$trackinfo['compilation']='0';
			else
				unset($trackinfo['compilation']);
		}
        if($remove_existing === true) {
            $process = new Process(['metaflac', '--remove-all', $outfile]); //Remove any existing metadata
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }

        $arguments = array('metaflac');

		foreach($options as $field_name=>$command_key) //Check which options we have data for and them to the command
		{
			if(!isset($trackinfo[$field_name]))
			    continue;
            if (is_array($trackinfo[$field_name]))
            {
                foreach ($trackinfo[$field_name] as $value)
                {
                    $arguments[] = sprintf('--set-tag=%s=%s', $command_key, $value);
                }
            }
            else
                $arguments[] = sprintf('--set-tag=%s=%s', $command_key, $trackinfo[$field_name]);
		}
		$arguments[] = $outfile;

		$process = new Process($arguments);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

		if(!empty($artwork)) //Write artwork
        {
            $process_artwork = new Process(['metaflac', '--import-picture-from='.$artwork, $outfile]);
            $process_artwork->run();
            if (!$process_artwork->isSuccessful()) {
                throw new ProcessFailedException($process_artwork);
            }
        }

		return $outfile;
	}

    /**
     * Write tags to m4a files using AtomicParsley
     * Write tags to flac files using metaflac
     * @param string $infile File to be renamed
     * @param string $outfile Where to save the renamed file
     * @param array $trackinfo Track info
     * @param string $artwork Artwork file to embed
     * @return string Renamed file
     * @throws Exception Failed to write metadata
     */
	public static function atomicparsley($infile,$outfile,$trackinfo,$artwork=null)
	{
	    if(!file_exists($infile))
	        throw new FileNotFoundException($infile);

		$arguments = array('AtomicParsley', $infile, '--output', $outfile);

		if(isset($trackinfo['compilation']))
		{
			if($trackinfo['compilation']===true)
				$trackinfo['compilation']='true';
			elseif($trackinfo['compilation']===false)
				$trackinfo['compilation']='false';
		}
        //TODO: Handle array values
		$options=array('artist'=>		'--artist=',
						'title'=>		'--title=',
						'album'=>		'--album=',
						'compilation'=>	'--compilation=');
		if(isset($trackinfo['tracknumber']) && isset($trackinfo['totaltracks'])) {
            $arguments[] = sprintf('--tracknum=%d/%d', $trackinfo['tracknumber'], $trackinfo['totaltracks']);
        }
		elseif(isset($trackinfo['tracknumber']))
		    $arguments[] = sprintf('--tracknum=%d', $trackinfo['tracknumber']);

		foreach($options as $option_key=>$option_value)
		{
			if(!isset($trackinfo[$option_key]))
				continue;
            $arguments[] = $option_value.$trackinfo[$option_key];
		}

		if(!empty($artwork)) {
            $arguments[] = '--artwork';
            $arguments[] = $artwork;
        }
		$process = new Process($arguments);

        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outfile;
	}

    /**
     * Write tags to FLAC files using metaflac or MPEG4 with AtomicParsley
     * @param string $infile File to be renamed
     * @param string $outfile Where to save the renamed file
     * @param array $metadata Metadata to be written
     * @param string $artwork Artwork file to embed
     * @return string Value of argument $outfile
     * @throws Exception Failed to write metadata
     */
	public static function write_metadata($infile, $outfile, $metadata, $artwork = null)
    {
        if(!empty($artwork) && !file_exists($artwork))
            throw new FileNotFoundException($artwork);

        $extension = pathinfo($infile, PATHINFO_EXTENSION);
        if($extension==='flac')
            return self::metaflac($infile, $outfile, $metadata, $artwork);
        elseif ($extension==='m4a' || $extension==='mp4')
            return self::atomicparsley($infile, $outfile, $metadata, $artwork);
        else
            throw new InvalidArgumentException(sprintf('Writing metadata to %s files is not supported', $extension));
    }

    /**
     * Get metadata from a file
     * @param $file
     * @return array
     * @throws FileNotFoundException
     */
	public static function read_metadata($file)
    {
        if(!file_exists($file) || !is_file($file))
            throw new FileNotFoundException($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if($extension==='flac') {
            $process = new Process(['metaflac', '--list', $file]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $metadata_raw = $process->getOutput();
            preg_match_all('/comment\[[0-9]+\]\: ([A-Z\_]+)=(.+)/', $metadata_raw, $metadata_raw2);
            $metadata_raw2[2] = array_map('trim', $metadata_raw2[2]);
            $metadata = [];
            $count = array_count_values($metadata_raw2[1]);
            foreach ($metadata_raw2[2] as $key => $value)
            {
                $field = $metadata_raw2[1][$key];
                if ($count[$field] > 1)
                    $metadata[$field][] = $value;
                else
                    $metadata[$field] = $value;
            }
            return $metadata;
        }
        else
            throw new InvalidArgumentException(sprintf('Reading metadata from %s files not supported', $extension));
    }
}
