<?php
// Copyright (c) 2018, chaolong.wu@qq.com
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions are met:
//     * Redistributions of source code must retain the above copyright
//       notice, this list of conditions and the following disclaimer.
//     * Redistributions in binary form must reproduce the above copyright
//       notice, this list of conditions and the following disclaimer in the
//       documentation and/or other materials provided with the distribution.
//     * Neither the name of the <organization> nor the
//       names of its contributors may be used to endorse or promote products
//       derived from this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
// ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
// WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
// DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
// DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
// (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
// LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
// ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
// (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
// SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

namespace Mp4;

class Info
{
    public static function get(string $mp4_file_path): array
    {
        $width    = 0;
        $height   = 0;
        $rotate   = 0;
        $decoded_len = 0;

        $fd = fopen($mp4_file_path, 'r');
        $file_info = fstat($fd);
        $total_len = $file_info['size']; // total file length

        do {
            $buffer = fread($fd, 8); // read in box header
            $box_header = unpack('Nsize/a4type', $buffer);

            $size = $box_header['size'];
            $type = $box_header['type'];

            $is_extended_size = (1 == $box_header['size']); // 64 bit extended size

            if ($is_extended_size) { // 64 bit extended size
                $buffer = fread($fd, 8); // read in 64 bit extended size
                $size  = self::unpack_u64($buffer);
            }

            if ('moov' == $type) {
                $decoded_len += 8 + ($is_extended_size ? 8 : 0);
                continue; // continue fread mvhd
            } else if ('trak' == $type) {
                $decoded_len += 8 + ($is_extended_size ? 8 : 0);
                continue; // continue fread tkhd
            } else if ('tkhd' == $type) {
                // structure of tkhd @see http://blog.sina.com.cn/s/blog_48f93b530100jz4b.html
                $buffer = fread($fd, 1); // version
                fread($fd, 3);           // flags
                $version = current( unpack('c', $buffer) );
                if (1 == $version) {
                    fread($fd, 8); // 64bit creation time
                    fread($fd, 8); // 64bit modification time
                    $d_len = 8;
                } else {
                    fread($fd, 4); // 32bit creation time
                    fread($fd, 4); // 32bit modification time
                    $d_len = 4;
                }
                              fread($fd, 4);       // track ID number cannot be repeated and cannot be 0
                $_timescale = fread($fd, 4);       // used to specify the scale value of the file media within 1 second, which can be understood as the number of time units of 1 second length
                $_duration  = fread($fd, $d_len);  // duration track
                              fread($fd, 8);       // reserved bits
                              fread($fd, 2);       // video layer, the default value is 0, the smaller the value, the upper layer
                              fread($fd, 2);       // alternate group track grouping information, the default value is 0, indicating that the track has no group relationship with other tracks
                              fread($fd, 2);       // volume [8.8] format, if it is an audio track, 1.0 (0x0100) means the maximum volume; otherwise 0
                              fread($fd, 2);       // reserved bits
                $matrix     = fread($fd, 36);      // video transformation matrix
                $_width     = fread($fd, 4);       // width
                $_height    = fread($fd, 4);       // height, all in [16.16] format, ratio of the actual screen size in the sample description, used for display width and height during playback

                $_width  = current( unpack('n2', $_width) );  // [16.16] format value
                $_height = current( unpack('n2', $_height) ); // [16.16] format value

                // there may be multiple tkhds, only valid values ​​are taken
                if ($_width || $_height) {
                    $width    = $_width;
                    $height   = $_height;
                }

                $matrix = unpack('N9', $matrix); // unpack has no parameters that can be converted to unsigned long (always 32 bit, big endian byte order). In the following comparison, we need to convert the signed -65536 to the unsigned 4294901760
                $display_matrix = [
                    [ $matrix[1], $matrix[2], $matrix[3] ],
                    [ $matrix[4], $matrix[5], $matrix[6] ],
                    [ $matrix[7], $matrix[8], $matrix[9] ],
                ];

                // assign clockwise rotate values based on transform matrix so that we can compensate for iPhone orientation during capture
                if ($display_matrix[1][0] == 4294901760/* -65536 */ && $display_matrix[0][1] == 65536) {
                    $rotate = 90;
                    break;
                }
                if ($display_matrix[0][0] == 4294901760/* -65536 */ && $display_matrix[1][1] == 4294901760/* -65536 */) {
                    $rotate = 180;
                    break;
                }
                if ($display_matrix[1][0] == 65536 && $display_matrix[0][1] == 4294901760/* -65536 */) {
                    $rotate = 270;
                    break;
                }
            }

            $decoded_len += $size;
        } while( $decoded_len < $total_len );

        fclose($fd);

        return [
            'rotate' => $rotate,
            'width'  => $width,
            'height' => $height,
        ];
    }

    private static function unpack_u64(string $str): int
    {
        $num64 = unpack('N2', $str);
        $size = ($num64[1] << 32) | $num64[2];

        return $size;
    }
}
