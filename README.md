BEncode Torrent
===============

PHP library for encoding and decoding BitTorrent BEncode data, focused around 
[Gazelle](https://github.com/ApolloRIP/Gazelle).

BEncode is the encoding used by BitTorrent to store and transmitting loosely structured data. It supports
* byte strings
* integers
* lists
* dictionaries (associative arrays, where keys are sorted alphabetically)

You can see more information about how these types are supported at 
[BitTorrentSpecification#Bencoding](https://wiki.theory.org/index.php/BitTorrentSpecification#Bencoding).

In addition to the above, torrent files are expected to be BEncoded dictionaries that contain minimally the keys
__announce__ (byte string) and __info__ (dictionary). Within the __info__ dictionary, we then expect __piece length__ 
(integer) and __pieces__ (byte string). If the torrent has only a single file, we then expect __name__ (byte string)
and __length__ (integer), whereas for a multi-file torrent, we'll have __name__ (byte string) and __files__ (list)
where each element is a dictionary that has the keys __length__ (integer) and __path__ (list of strings).

As such, this library will make some checks when loading data that these mandatory fields exist or else an Exception is
raised. More information on these fields can be found at 
[BitTorrentSpecification#Metainfo_File_Structure](https://wiki.theory.org/index.php/BitTorrentSpecification#Metainfo_File_Structure).

Finally, this library is primarily aimed at being used within the [Gazelle](https://github.com/ApolloRIP/Gazelle) so
we have some utility functions within the library that make sense there to accomplish the following things:
* Ensuring torrent files are marked as 'private'
* Setting a 'source' on torrents (to ensure unique info hash)
* Cleaning out unnecessary fields that also reveal stuff about a user (like __announce list__ and __created by__)
* Generate string file lists as expected by Gazelle for display

This is based (loosely) off the code in the two separate BEncode libraries within WCD's Gazelle 
([bencodetorrent.class.php](https://github.com/WhatCD/Gazelle/blob/master/classes/bencodetorrent.class.php) and 
[torrent.class.php](https://github.com/WhatCD/Gazelle/blob/master/classes/torrent.class.php)), but without the
necessary 32bit shims as well as make it a unified library used for both uploading and downloading the torrent files.