<?php

namespace Wolfrack\Library;

use Wolfrack\Library\Exception\EmptyUrlException;
use Wolfrack\Library\Exception\PasswordException;
use Wolfrack\Library\Exception\WrongPasswordException;
use Wolfrack\Library\Exception\YoutubedlException;
use stdClass;

class Video
{

    private $webpageUrl;

    private $requestedFormat;

    private $password;

    private $json;

    private $urls;

    private $downloader;

    public function __construct(
        Downloader $downloader,
        string $webpageUrl,
        string $requestedFormat,
        string $password = null
    ) {
        $this->downloader = $downloader;
        $this->webpageUrl = $webpageUrl;
        $this->requestedFormat = $requestedFormat;
        $this->password = $password;
    }

    public function getProp($prop = 'dump-json')
    {
        $arguments = ['--' . $prop];

        if (isset($this->webpageUrl)) {
            $arguments[] = $this->webpageUrl;
        }
        if (isset($this->requestedFormat)) {
            $arguments[] = '-f';
            $arguments[] = $this->requestedFormat;
        }
        if (isset($this->password)) {
            $arguments[] = '--video-password';
            $arguments[] = $this->password;
        }

        return $this->downloader->callYoutubedl($arguments);
    }

    public function getJson()
    {
        if (!isset($this->json)) {
            $this->json = json_decode($this->getProp('dump-single-json'));
        }

        return $this->json;
    }

    public function __get(string $name)
    {
        if (isset($this->$name)) {
            return $this->getJson()->$name;
        }

        return null;
    }

    public function __isset(string $name)
    {
        return isset($this->getJson()->$name);
    }

    public function getUrl()
    {
        if (!isset($this->urls)) {
            $this->urls = explode("\n", $this->getProp('get-url'));

            if (empty($this->urls[0])) {
                throw new EmptyUrlException();
            }
        }

        return $this->urls;
    }

    public function getFilename()
    {
        return trim($this->getProp('get-filename'));
    }

    public function getFileNameWithExtension(string $extension)
    {
        if (isset($this->ext)) {
            return str_replace('.' . $this->ext, '.' . $extension, $this->getFilename());
        } else {
            return $this->getFilename() . '.' . $extension;
        }
    }

    public function getRtmpArguments()
    {
        $arguments = [];

        if ($this->protocol == 'rtmp') {
            foreach (
                [
                    'url' => '-rtmp_tcurl',
                    'webpage_url' => '-rtmp_pageurl',
                    'player_url' => '-rtmp_swfverify',
                    'flash_version' => '-rtmp_flashver',
                    'play_path' => '-rtmp_playpath',
                    'app' => '-rtmp_app',
                ] as $property => $option
            ) {
                if (isset($this->{$property})) {
                    $arguments[] = $option;
                    $arguments[] = $this->{$property};
                }
            }

            if (isset($this->rtmp_conn)) {
                foreach ($this->rtmp_conn as $conn) {
                    $arguments[] = '-rtmp_conn';
                    $arguments[] = $conn;
                }
            }
        }

        return $arguments;
    }

    public function withFormat(string $format)
    {
        return new self($this->downloader, $this->webpageUrl, $format, $this->password);
    }
}
