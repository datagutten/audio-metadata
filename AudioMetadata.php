<?Php
require 'vendor/autoload.php';
class AudioMetadata
{
	private $dependcheck;
	public $fields=array('title','artist','album','tracknumber','totaltracks','compilation'); //albumartist
	public $debug=false;
	public $error;
	function __construct()
	{
		$this->dependcheck=new dependcheck;
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
		if(!empty($trackinfo['track']))
			$trackname=sprintf("%02d %s",$trackinfo['track'],$trackname);
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
     * @return bool|string
     * @throws Exception
     */
	function metadata($infile,$outpath,$trackinfo)
	{
		if(!file_exists($infile) || !is_file($infile))
		{
			throw new InvalidArgumentException("$infile does not exist or is not a file");
		}
		$extension=pathinfo($infile,PATHINFO_EXTENSION);
		$filename=$this->build_file_name($trackinfo,$extension);
		$album_dir=$outpath.'/'.$this->build_directory_name($trackinfo,$extension);
		$output_file=$album_dir.'/'.$filename;

		if(!file_exists($album_dir))
			mkdir($album_dir,0777,true);

		if(empty($trackinfo['cover']))
			$artwork_file=false;
		else
		{
			$artwork_extension=pathinfo($trackinfo['cover'],PATHINFO_EXTENSION);
			$artwork_file=$album_dir.'/'.$trackinfo['album'].'.'.$artwork_extension;
			if(!file_exists($artwork_file))
				copy($trackinfo['cover'],$artwork_file);
		}

		if(file_exists($output_file))
		{
			$this->error="$output_file exists";
			return false;
		}
		if($extension=='flac')
			return $this->metaflac($infile,$output_file,$trackinfo,$artwork_file);
		elseif($extension=='m4a')
			return $this->atomicparsley($infile,$output_file,$trackinfo,$artwork_file);
		else
		{
			$this->error="Unsupported file extension: $extension";
			return false;
		}
	}

    /**
     * Write tags to flac files using metaflac
     * @param string $infile File to be renamed
     * @param string $outfile Where to save the renamed file
     * @param array $trackinfo Track info
     * @param string $artwork Artwork file to embed
     * @return string Renamed file
     * @throws DependencyFailedException metaflac not found
     * @throws Exception Failed to write metadata
     */
	public function metaflac($infile,$outfile,$trackinfo,$artwork=null)
	{
		$this->dependcheck->depend('metaflac');

		if(substr($infile,-4,4)!='flac')
			throw new InvalidArgumentException('File must have flac extension');

		copy($infile,$outfile);
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
		shell_exec($cmd=sprintf('metaflac --remove-all %s',escapeshellarg($outfile))); //Remove any existing metadata
		$cmd='metaflac';
		foreach($options as $field_name=>$command_key) //Check which options we have data for and them to the command
		{
			if(!isset($trackinfo[$field_name]))
			{
				if($this->debug)
					echo "No value for $field_name\n";
				continue;
			}
			$cmd.=sprintf(' --set-tag=%s',escapeshellarg($command_key.'='.$trackinfo[$field_name]));
		}
		$cmd.=' '.escapeshellarg($outfile); //Add the filename to the command
		exec($cmd." 2>&1",$output,$return);

		if($return!=0)
		{
			throw new Exception(implode("\n",$output));
		}

		if(!empty($artwork) && file_exists($artwork)) //Write artwork
			shell_exec($cmd=sprintf("metaflac --import-picture-from=%s %s",escapeshellarg($artwork),escapeshellarg($outfile)));
		return implode("\n",$output);
	}

    /**
     * Write tags to m4a files using AtomicParsley
     * Write tags to flac files using metaflac
     * @param string $infile File to be renamed
     * @param string $outfile Where to save the renamed file
     * @param array $trackinfo Track info
     * @param string $artwork Artwork file to embed
     * @return string Renamed file
     * @throws DependencyFailedException AtomicParsley not found
     * @throws Exception Failed to write metadata
     */
	public function atomicparsley($infile,$outfile,$trackinfo,$artwork=null)
	{
		$this->dependcheck->depend('AtomicParsley');
		$cmd=sprintf('AtomicParsley "%s" --output "%s"',$infile,$outfile);

		if(isset($trackinfo['compilation']))
		{
			if($trackinfo['compilation']===true)
				$trackinfo['compilation']='true';
			elseif($trackinfo['compilation']===false)
				$trackinfo['compilation']='false';
		}
		$options=array('artist'=>		'--artist="%s"',
						'title'=>		'--title="%s"',
						'album'=>		'--album="%s"',
						'tracknumber'=>	'--tracknum=%d',
						'compilation'=>	'--compilation="%s"');
		if(isset($trackinfo['tracknumber']) && isset($trackinfo['totaltracks']))
			$options['tracknumber']=sprintf('--tracknum=%d/%d',$trackinfo['tracknumber'],$trackinfo['totaltracks']);

		foreach($options as $option_key=>$option_value)
		{
			if(!isset($trackinfo[$option_key]))
			{
				if($this->debug)
					echo "No value for $option_key\n";
				continue;
			}
			$cmd.=sprintf(' '.$option_value,$trackinfo[$option_key]);
		}

		if(!empty($artwork) && file_exists($artwork))
			$cmd.=" --artwork ".escapeshellarg($artwork);

		exec($cmd." 2>&1",$output,$return);
		if($return!=0)
		{
			throw new Exception(implode("\n",$output));
		}
		return implode("\n",$output);
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

        if($extension==='flac')
        {
            $metadata_raw=shell_exec(sprintf('metaflac --list "%s"',$file));
            preg_match_all('/comment\[[0-9]+\]\: ([A-Z\_]+)=(.+)/',$metadata_raw,$metadata_raw2);
            $metadata=array_combine($metadata_raw2[1],$metadata_raw2[2]);
            return $metadata;
        }
        else
            throw new InvalidArgumentException(sprintf('Reading metadata from %s files not supported', $extension));
    }
}
