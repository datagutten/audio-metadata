<?Php
class metadata
{
	private $dependcheck;
	public $fields=array('title','artist','album','tracknumber','totaltracks','compilation'); //albumartist
	public $debug=false;
	public $error;
	function __construct()
	{
		require_once 'tools/dependcheck.php';
		require_once 'tools/filnavn.php';
		$this->dependcheck=new dependcheck;
	}
	function filnavn($tittel)
	{
		//Call global function from tools submodule
		return filnavn($tittel);
	}
	function buildfilename($trackinfo)
	{
		if(isset($trackinfo['compilation']) && $trackinfo['compilation']===true) //Artist skal bare være med i filnavn hvis det er et samlealbum
			$trackname=sprintf('%s - %s',$trackinfo['artist'],$trackinfo['title']);
		else
			$trackname=$trackinfo['title'];
		if(!empty($trackinfo['track']))
			$trackname=sprintf("%02d %s",$trackinfo['track'],$trackname);
		if(!empty($trackinfo['totalvolumes']) && $trackinfo['totalvolumes']>1) //Multi volume album
			$trackname=sprintf("%02d %s",$trackinfo['volumenumber'],$trackname);
		return $this->filnavn($trackname);
	}

	function metadata($infile,$outpath,$trackinfo)
	{
		if(!file_exists($infile) || !is_file($infile))
		{
			$this->error="$infile does not exist or is not a file";
			return false;
		}
		$filename=$this->buildfilename($trackinfo);

		$extension=pathinfo($infile,PATHINFO_EXTENSION);
		
		if(!empty($trackinfo['albumartist']))
			$albumname=$this->filnavn(sprintf('%s - %s',$trackinfo['albumartist'],$trackinfo['album']));
		else
			$albumname=$this->filnavn($trackinfo['album']); //No album artist
		if(!empty($trackinfo['year']))
			$albumname=sprintf('%s (%d)',$albumname,$trackinfo['year']);
		$albumname.=' '.strtoupper($extension);
		$outpath.='/'.$albumname;

		if(!file_exists($outpath))
			mkdir($outpath,0777,true);

		if(empty($trackinfo['cover']))
			$artwork=false;
		elseif(!file_exists($artwork=$outpath.'/'.$albumname.'.'.pathinfo($trackinfo['cover'],PATHINFO_EXTENSION)))
			copy($trackinfo['cover'],$artwork);

		$outfile=sprintf('%s/%s.%s',$outpath,$filename,$extension);
		if(file_exists($outfile))
		{
			$this->error="$outfile exists";
			return false;
		}
		if($extension=='flac')
			return $this->metaflac($infile,$outfile,$trackinfo,$artwork);
		elseif($extension=='m4a')
			return $this->atomicparsley($infile,$outfile,$trackinfo,$artwork);
		else
		{
			$this->error="Unsupported file extension: $extension";
			return false;
		}
	}
	public function metaflac($infile,$outfile,$trackinfo,$artwork=false)
	{
		if($this->dependcheck->depend('metaflac')!==true)
		{
			$this->error="Metaflac not found, unable to write flac metadata";
			return false;
		}
		if(substr($infile,-4,4)!='flac')
			throw new Exception('File must have flac extension');

		copy($infile,$outfile);
		$options=array( 'artist'=>		'--set-tag="ARTIST=%s"',
						'title'=>		'--set-tag="TITLE=%s"',
						'album'=>		'--set-tag="ALBUM=%s"',
						'tracknumber'=>	'--set-tag="TRACKNUMBER=%s"',
						'totaltracks'=>	'--set-tag="TRACKTOTAL=%s"',
						'compilation'=>	'--set-tag="COMPILATION=%s"',
						'isrc'=>		'--set-tag="ISRC=%s"',
						'year'=>		'--set-tag="YEAR=%s"',
						'copyright'=>	'--set-tag="COPYRIGHT=%s"');
		if(isset($trackinfo['compilation']))
		{
			if($trackinfo['compilation']===true)
				$trackinfo['compilation']='1';
			elseif($trackinfo['compilation']===false)
				$trackinfo['compilation']='0';
			else
				unset($trackinfo['compilation']);
		}
		shell_exec($cmd="metaflac --remove-all \"$outfile\""); //Remove any existing metadata
		$cmd='metaflac';
		foreach($options as $option_key=>$option_value) //Check which options we have data for and them to the command
		{
			if(!isset($trackinfo[$option_key]))
			{
				if($this->debug)
					echo "No value for $option_key\n";
				continue;
			}
			$cmd.=sprintf(' '.$option_value,$trackinfo[$option_key]);
		}
		$cmd.=' "'.$outfile.'"'; //Add the filename to the command

		exec($cmd." 2>&1",$output,$return);

		if($return!=0)
		{
			$this->error=implode("\n",$output);
			return false;
		}

		if($artwork!==false && file_exists($artwork)) //Write artwork
			shell_exec($cmd="metaflac --import-picture-from=\"$artwork\" \"$outfile\"");
		return implode("\n",$output);
	}

	public function atomicparsley($infile,$outfile,$trackinfo,$artwork=false)
	{
		if($this->dependcheck->depend('AtomicParsley')!==true)
		{
			$this->error="AtomicParsley is not installed, unable to tag m4a files";
			return false;
		}
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

		if($artwork!==false && file_exists($artwork))
			$cmd.=" --artwork \"$artwork\"";

		exec($cmd." 2>&1",$output,$return);
		if($return!=0)
		{
			$this->error=implode("\n",$output);
			return false;
		}
		return implode("\n",$output);
	}
	/*
	Convert a file to flac
	The file is converted via wav, so all metadata are removed
	The flac file is saved in the same directory as the source file
	*/
	function convert_to_flac($file)
	{
		if(($missing=$this->dependcheck->depend(array('flac','ffmpeg')))!==true)
		{
			$this->error='Missing required tools to convert files: '.implode("\n",$missing);
			return false;
		}
		if(!file_exists($file))
			return false;
		$pathinfo=pathinfo($file);
		$tmpfile='/tmp/'.$pathinfo['basename'].'wav';
		$flac_file=sprintf('%s/%s.flac',$pathinfo['dirname'],$pathinfo['filename']);
		shell_exec(sprintf('ffmpeg -n -i "%s" -f wav "%s" 2>&1',$file,$tmpfile)); //Convert to temporary wav file
		//shell_exec(sprintf('ffmpeg -n -i "%s" -f wav "%s"',$file,$tmpfile)); //Convert to temporary wav file
		if(!file_exists($tmpfile))
		{
			$this->error='Error converting to temporary wav file';
			return false;
		}
		shell_exec(sprintf('flac -s -o "%s/%s.flac" "%s"',$pathinfo['dirname'],$pathinfo['filename'],$tmpfile)); //Convert wav to flac

		if(!file_exists($flac_file))
		{
			$this->error='Error converting to flac';
			return false;
		}
		unlink($tmpfile); //Remove temporary wav file
		return $flac_file;
	}
}
