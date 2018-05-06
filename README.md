# php-GD-Animated-webp
First attempt to create an animated webp using GD

Based on https://es.stackoverflow.com/q/159217/81450

Note: Missing the gif frames extraction things

PART 1 : encoding each frame

1) we use GD's `imagewebp` to encode the image frame and capture the data with `ob_start`
2) discard file header, keep framedata payload (starts with "VP8 " and goes until the eof)
3) save into an array(buffer): framedata + width + height + frame duration
4) repeat until no mo frames

PART 2 : build our own animated webp file according to specs
https://developers.google.com/speed/webp/docs/riff_container

> An animated image with EXIF metadata may look as follows:
```
RIFF/WEBP
+- VP8X (descriptions of features used)
+- ANIM (global animation parameters)
+- ANMF (frame1 parameters + data)
+- ANMF (frame2 parameters + data)
+- ANMF (frame3 parameters + data)
+- ANMF (frame4 parameters + data)
+- EXIF (metadata) <- optional we wont use it
```

1) header fileformat (RIFF+filesize+WEBP)
1.a) calculate filesize (uint32) total bytes in file -8 from header, or total bytes in content chunks + 4 

2) chunk header VP8X 
2.a) calculate chunksize (uint32) = 10
2.b) set bit flags alpha and animation to 1 
2.c) store canvas width and height (uint24) (-1)

3) chunk header ANIM
2.a) calculate chunksize (uint32) = 6
3.b) background color: BGRA (0,0,0,0)
3.c) loop count (uint16) 0=infinityandbeyond

4) for each frame stored in array (buffer) PART 1.3
4.a) chunk header ANMF
2.b) calculate chunksize (uint32) = 16 + total bytes in frameData
4.c) frame origin X,Y (uint24) (/2?)
4.d) frame width and height (uint24) (-1)
4.e) duration in miliseconds (uint24)
4.f) reserved (6 bits) + alpha blending (1 bit) + discard frame (1 bit)
4.g) frameData from array (buffer) PARTE 1.3

5) save to disk, send to browser or close stream

