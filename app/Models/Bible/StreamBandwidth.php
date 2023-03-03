<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;

class StreamBandwidth extends Model
{
    public const CODEC_AUDIO = 'mp4a.40.2';
    public const CODEC_VIDEO = 'avc1.4d001f';
    public const CODEC_VIDEO_AND_AUDIO = 'avc1.4d001f,mp4a.40.2';

    protected $connection = 'dbp';
    protected $table = 'bible_file_stream_bandwidths';
    protected $fillable = ['file_id','file_name','bandwidth','resolution_width','resolution_height','codec','stream'];

    public function file()
    {
        return $this->belongsTo(BibleFile::class, 'bible_file_id', 'id');
    }

    public function transportStreamTS()
    {
        return $this->hasMany(StreamTS::class);
    }

    public function transportStreamBytes()
    {
        return $this->hasMany(StreamBytes::class)->orderBy('offset');
    }

    /**
     * Removes the specified video codec from the list of codecs and returns the remaining codecs as a string
     * for the current instance
     *
     * @return string
     */
    public function getCodecWithoutVideoCodec() : string
    {
        $codecs = \explode(",", $this->codec);
        $newcodecs = [];

        foreach ($codecs as $codec) {
            if ($codec !== self::CODEC_VIDEO) {
                $newcodecs[] = $codec;
            }
        }

        if (!empty($newcodecs)) {
            return \implode(",", $newcodecs);
        }
    
        return $this->codec;
    }

    /**
     * Check if current instance of stream bandwith belongs to audio stream
     *
     * @return bool
     */
    public function isAudio() : bool
    {
        return is_null($this->resolution_width) || empty($this->resolution_width);
    }

    /**
     * Create a Playlist content using the current instance of StreamBandwidth class
     *
     * @param string $key
     * @param string $asset_id
     *
     * @return string
     */
    public function getPlaylistContent(string $key, string $asset_id) : string
    {
        $content = "\n#EXT-X-STREAM-INF:PROGRAM-ID=1,BANDWIDTH={$this->bandwidth}";

        $transportStream = sizeof($this->transportStreamBytes)
            ? $this->transportStreamBytes
            : $this->transportStreamTS;

        $extrargs = '';

        if (sizeof($transportStream) &&
            isset($transportStream[0]->timestamp) &&
            $transportStream[0]->timestamp->verse_start === 0
        ) {
            $extrargs = '&v0=0';
        }

        if ($this->resolution_width) {
            $content .= ',RESOLUTION=' . $this->resolution_width . "x{$this->resolution_height}";
        }

        if ($this->codec) {
            if ($this->isAudio()) {
                $scodecs = $this->getCodecWithoutVideoCodec();
            } else {
                $scodecs = $this->codec;
            }
            $content .= ",CODECS=\"$scodecs\"";
        }
        $content .= "\n{$this->file_name}" . '?key=' . $key . '&v=4&asset_id=' . $asset_id . $extrargs;

        return $content;
    }
}
