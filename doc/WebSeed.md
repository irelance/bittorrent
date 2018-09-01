#1. WebSeed
使用http、ftp等网络资源作为永久资源
HTTPS、FTPS、SFTP、RTSP、MMS、NNTP

matedata中加入url-list字段

如果url以/结尾，则在其后添加name补全文件名，
多文件除了添加name外还继续根据files的path继续补全

注意：HTTP不一定支持byte-ranges，FTP也只支持从哪里开始
同一个ip建立了多个连接（例如 100个）可能会被认为是攻击

