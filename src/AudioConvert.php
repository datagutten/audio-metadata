<?php

namespace datagutten\AudioMetadata;

use dependcheck;
use DependencyFailedException;
use Exception;
use FileNotFoundException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AudioConvert
{
    /**
     * @throws DependencyFailedException
     */
    public static function check_dependencies()
    {
        $dependcheck=new dependcheck;
        $dependcheck->depend('flac');
        $dependcheck->depend('ffmpeg');
    }

    /**
     * Convert a file to wav using ffmpeg
     * @param string $file File to be converted
     * @param string $converted_file File name for the converted file
     * @return string Converted file
     * @throws FileNotFoundException Thrown when input file is not found
     * @throws Exception Thrown when conversion fails
     */
    public static function convert_to_wav($file, $converted_file = null)
    {
        if (!file_exists($file))
            throw new FileNotFoundException($file);
        $pathinfo = pathinfo($file);
        if(empty($converted_file))
            $converted_file = sprintf('%s/%s.wav', $pathinfo['dirname'], $pathinfo['filename']);
        if(file_exists($converted_file))
            return $converted_file;

        $process_ffmpeg = new Process(['ffmpeg', '-n', '-i', $file, '-f', 'wav', $converted_file]);
        $process_ffmpeg->run();

        if (!$process_ffmpeg->isSuccessful()) {
            throw new ProcessFailedException($process_ffmpeg);
        }

        return $converted_file;
    }

    /**
     *  Convert a file to flac
     *  The file is converted via wav, so all metadata are removed
     *  The flac file is saved in the same directory as the source file
     * @param string $file File to be converted
     * @param string $converted_file File name for the converted file
     * @return string Converted flac file
     * @throws FileNotFoundException Thrown when input file is not found
     * @throws Exception Thrown when conversion fails
     */
    public static function convert_to_flac($file, $converted_file = null)
    {
        if (!file_exists($file))
            throw new FileNotFoundException($file);

        $pathinfo = pathinfo($file);
        if(empty($converted_file))
            $flac_file = sprintf('%s/%s.flac', $pathinfo['dirname'], $pathinfo['filename']);
        else
            $flac_file = $converted_file;

        if(file_exists($flac_file))
            return $flac_file;

        $tmp_file = sys_get_temp_dir() . '/' . $pathinfo['basename'] . '.wav';
        if(file_exists($tmp_file))
            unlink($tmp_file);
        self::convert_to_wav($file, $tmp_file);

        $process_flac = new Process(['flac', '-s', '-o', $flac_file, $tmp_file]);
        $process_flac->run();

        if (!$process_flac->isSuccessful()) {
            throw new ProcessFailedException($process_flac);
        }

        unlink($tmp_file); //Remove temporary wav file
        return $flac_file;
    }
}