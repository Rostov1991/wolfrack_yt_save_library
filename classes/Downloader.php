<?php

namespace Wolfrack\Library;

use Wolfrack\Library\Exception\WolfrackLibraryException;
use Wolfrack\Library\Exception\AvconvException;
use Wolfrack\Library\Exception\EmptyUrlException;
use Wolfrack\Library\Exception\InvalidProtocolConversionException;
use Wolfrack\Library\Exception\InvalidTimeException;
use Wolfrack\Library\Exception\PasswordException;
use Wolfrack\Library\Exception\PlaylistConversionException;
use Wolfrack\Library\Exception\PopenStreamException;
use Wolfrack\Library\Exception\RemuxException;
use Wolfrack\Library\Exception\WrongPasswordException;
use Wolfrack\Library\Exception\YoutubedlException;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

class Downloader
{

    private $youtubedl;

    private $python;

    private $avconv;

    private $avconvVerbosity;

    private $phantomjsDir;

    private $params;

    private $logger;

    public function __construct(
        $youtubedl = '/usr/bin/youtube-dl',
        array $params = ['--no-warnings'],
        $python = '/usr/bin/python3',
        $avconv = '/usr/bin/ffmpeg',
        $phantomjsDir = '/usr/bin/',
        $avconvVerbosity = 'error'
    ) {
        $this->youtubedl = $youtubedl;
        $this->params = $params;
        $this->python = $python;
        $this->avconv = $avconv;
        $this->phantomjsDir = $phantomjsDir;
        $this->avconvVerbosity = $avconvVerbosity;

        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getVideo(string $webpageUrl, $requestedFormat = 'best/bestvideo', string $password = null)
    {
        return new Video($this, $webpageUrl, $requestedFormat, $password);
    }

    private function getProcess(array $arguments)
    {
        return new Process(
            array_merge(
                [$this->python, $this->youtubedl],
                $this->params,
                $arguments
            )
        );
    }

    public static function checkCommand(array $command)
    {
        $process = new Process($command);
        $process->run();

        return $process->isSuccessful();
    }

    private function getAvconvProcess(
        Video $video,
        int $audioBitrate,
        $filetype = 'mp3',
        $audioOnly = true,
        string $from = null,
        string $to = null
    ) {
        if (!$this->checkCommand([$this->avconv, '-version'])) {
            throw new AvconvException($this->avconv);
        }

        $durationRegex = '/(\d+:)?(\d+:)?(\d+)/';

        $afterArguments = [];

        if ($audioOnly) {
            $afterArguments[] = '-vn';
        }

        if (!empty($from)) {
            if (!preg_match($durationRegex, $from)) {
                throw new InvalidTimeException($from);
            }
            $afterArguments[] = '-ss';
            $afterArguments[] = $from;
        }
        if (!empty($to)) {
            if (!preg_match($durationRegex, $to)) {
                throw new InvalidTimeException($to);
            }
            $afterArguments[] = '-to';
            $afterArguments[] = $to;
        }

        $urls = $video->getUrl();

        $arguments = array_merge(
            [
                $this->avconv,
                '-v', $this->avconvVerbosity,
            ],
            $video->getRtmpArguments(),
            [
                '-i', $urls[0],
                '-f', $filetype,
                '-b:a', $audioBitrate . 'k',
            ],
            $afterArguments,
            [
                'pipe:1',
            ]
        );

        $arguments[] = '-user_agent';
        $arguments[] = $video->getProp('dump-user-agent');

        $process = new Process($arguments);
        $this->logger->debug($process->getCommandLine());

        return $process;
    }

    public function callYoutubedl(array $arguments)
    {
        $process = $this->getProcess($arguments);
        $process->setEnv(['PATH' => $this->phantomjsDir]);
        $this->logger->debug($process->getCommandLine());
        $process->run();
        if (!$process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $exitCode = intval($process->getExitCode());
            if ($errorOutput == 'ERROR: This video is protected by a password, use the --video-password option') {
                throw new PasswordException($errorOutput, $exitCode);
            } elseif (substr($errorOutput, 0, 21) == 'ERROR: Wrong password') {
                throw new WrongPasswordException($errorOutput, $exitCode);
            } else {
                throw new YoutubedlException($process);
            }
        } else {
            return trim($process->getOutput());
        }
    }
	
    public function getM3uStream(Video $video)
    {
        if (!$this->checkCommand([$this->avconv, '-version'])) {
            throw new AvconvException($this->avconv);
        }

        $urls = $video->getUrl();

        $process = new Process(
            [
                $this->avconv,
                '-v', $this->avconvVerbosity,
                '-i', $urls[0],
                '-f', $video->ext,
                '-c', 'copy',
                '-bsf:a', 'aac_adtstoasc',
                '-movflags', 'frag_keyframe+empty_moov',
                'pipe:1',
            ]
        );

        $stream = popen($process->getCommandLine(), 'r');
        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }

    public function getAudioStream(Video $video, $audioBitrate = 128, string $from = null, string $to = null)
    {
        return $this->getConvertedStream($video, $audioBitrate, 'mp3', true, $from, $to);
    }

    public function getRemuxStream(Video $video)
    {
        $urls = $video->getUrl();

        if (!isset($urls[0]) || !isset($urls[1])) {
            throw new RemuxException('This video does not have two URLs.');
        }

        $process = new Process(
            [
                $this->avconv,
                '-v', $this->avconvVerbosity,
                '-i', $urls[0],
                '-i', $urls[1],
                '-c', 'copy',
                '-map', '0:v:0',
                '-map', '1:a:0',
                '-f', 'matroska',
                'pipe:1',
            ]
        );

        $stream = popen($process->getCommandLine(), 'r');
        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }

    public function getRtmpStream(Video $video)
    {
        $urls = $video->getUrl();

        $process = new Process(
            array_merge(
                [
                    $this->avconv,
                    '-v', $this->avconvVerbosity,
                ],
                $video->getRtmpArguments(),
                [
                    '-i', $urls[0],
                    '-f', $video->ext,
                    'pipe:1',
                ]
            )
        );
        $stream = popen($process->getCommandLine(), 'r');
        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }

    public function getConvertedStream(
        Video $video,
        int $audioBitrate,
        string $filetype,
        $audioOnly = false,
        string $from = null,
        string $to = null
    ) {
        if (isset($video->_type) && $video->_type == 'playlist') {
            throw new PlaylistConversionException();
        }

        if (isset($video->protocol) && in_array($video->protocol, ['m3u8', 'm3u8_native', 'http_dash_segments'])) {
            throw new InvalidProtocolConversionException($video->protocol);
        }

        if (count($video->getUrl()) > 1) {
            throw new RemuxException('Can not convert and remux at the same time.');
        }

        $avconvProc = $this->getAvconvProcess($video, $audioBitrate, $filetype, $audioOnly, $from, $to);

        $stream = popen($avconvProc->getCommandLine(), 'r');

        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }

    public function getExtractors()
    {
        return explode("\n", trim($this->callYoutubedl(['--list-extractors'])));
    }

    public function getHttpResponse(Video $video, array $headers = [])
    {
        $client = new Client(['idn_conversion' => false]);
        $urls = $video->getUrl();
        $stream_context_options = [];

        if (array_key_exists('Referer', (array)$video->http_headers)) {
            $stream_context_options = [
                'http' => [
                    'header' => 'Referer: ' . $video->http_headers->Referer
                ]
            ];
        }

        return $client->request(
            'GET',
            $urls[0],
            [
                'stream' => true,
                'stream_context' => $stream_context_options,
                'headers' => array_merge((array)$video->http_headers, $headers)
            ]
        );
    }
}
