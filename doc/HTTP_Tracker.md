#1. HTTP Tracker

#1.1. Request
GET

params:

    info_hash   torrent的info hash
    peer_id     peer的id
    port        peer监听的port
    uploaded
    downloaded
    left
    ip          optional    peer的ip或域名
    event       optional    [started, completed, stopped]
    compact     optional    [0, 1]

#1.2. Response
Bencode字典

    interval
    peers       基本模式返回peer id, ip, port的字典，compact返回<ip,port>的字符串


#1.3. Error
Bencode字典

    failure reason
    retry in        int|"never"     //草案
    
