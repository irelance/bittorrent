#0. KRPC Protocol
使用UDP协议的RPC，payload是bencoded字典，一个请求返回一个响应，不重发（可能掉包）
- "peer" 是在一个 TCP 端口上监听的客户端/服务器，它实现了 BitTorrent 协议。
- "node" 是在一个 UDP 端口上监听的客户端/服务器，它实现了 DHT(分布式哈希表) 协议。


#1. 参考文档
http://www.bittorrent.org/beps/bep_0005.html

#2. 请求 DHT Queries
```json
{
  "t":"",   //必传，请求者生成，通常2字节char表示的short int，响应体返回相同值
  "y":"q",  //必传，消息类型，1字节char，{"q":query, "r":response, "e":error}
  "v":"",   //可传，客户端版本，2字节char，参考BEP 20
  "q":"",   //必传，请求类型，["ping", "find_node", "get_peers", "announce_peer"]
  "a":{}    //请求体
}
```
请求体具体见[请求类型与响应](#4)

#3. 响应
#3.1. 正常
```json
{
  "t":"",   //必传，请求者生成，通常2字节char表示的short int，响应体返回相同值
  "y":"r",  //必传，消息类型，1字节char，{"q":query, "r":response, "e":error}
  "v":"",   //可传，客户端版本，2字节char，参考BEP 20
  "r":{}    //响应体
}
```
响应体具体见[请求类型与响应](#4)

#3.2. 错误
```json
{
  "t":"",   //必传，请求者生成，通常2字节char表示的short int，响应体返回相同值
  "y":"e",  //必传，消息类型，1字节char，{"q":query, "r":response, "e":error}
  "v":"",   //可传，客户端版本，2字节char，参考BEP 20
  "e":[]    //错误 [(int) code, (string) message]
}
```
错误类型

 code | message
 ---: | :---
  201 |Generic Error
  202 |Server Error
  203 |Protocol Error, such as a malformed packet, invalid arguments, or bad token
  204 |Method Unknown

#4. 请求类型与响应
##4.1.ping
    arguments:  {"id" : "<querying nodes id>"}
    response: {"id" : "<queried nodes id>"}

Example Packets

    ping Query = {"t":"aa", "y":"q", "q":"ping", "a":{"id":"abcdefghij0123456789"}}
    bencoded = d1:ad2:id20:abcdefghij0123456789e1:q4:ping1:t2:aa1:y1:qe
    Response = {"t":"aa", "y":"r", "r": {"id":"mnopqrstuvwxyz123456"}}
    bencoded = d1:rd2:id20:mnopqrstuvwxyz123456e1:t2:aa1:y1:re

##4.2.find_node
根据```<id of target node>```返回target的node或K(8)邻近的节点。

```<compact node info>``` == nodeId(20) + ip(4) + port(2) 

    arguments:  {"id" : "<querying nodes id>", "target" : "<id of target node>"}
    response: {"id" : "<queried nodes id>", "nodes" : "<compact node info>"}
    
Example Packets

    find_node Query = {"t":"aa", "y":"q", "q":"find_node", "a": {"id":"abcdefghij0123456789", "target":"mnopqrstuvwxyz123456"}}
    bencoded = d1:ad2:id20:abcdefghij01234567896:target20:mnopqrstuvwxyz123456e1:q9:find_node1:t2:aa1:y1:qe
    Response = {"t":"aa", "y":"r", "r": {"id":"0123456789abcdefghij", "nodes": "def456..."}}
    bencoded = d1:rd2:id20:0123456789abcdefghij5:nodes9:def456...e1:t2:aa1:y1:re

##4.3.get_peers
当节点要为 torrent 寻找 peer 时，
它将自己路由表中的节点 ID 和 torrent 的 infohash 进行"距离对比"。
然后向路由表中离 infohash 最近的节点发送请求，
问它们正在下载这个 torrent 的 peer 的联系信息。
如果一个被联系的节点知道下载这个 torrent 的 peer 信息，
那个 peer 的联系信息将被回复给当前节点。
否则，那个被联系的节点则必须回复在它的路由表中离该 torrent 的 infohash 最近的节点的联系信息。
最初的节点重复地请求比目标 infohash 更近的节点，直到不能再找到更近的节点为止。
查询完了之后，客户端把自己作为一个 peer 插入到所有回复节点中离种子最近的那个节点中。

    arguments:  {"id" : "<querying nodes id>", "info_hash" : "<20-byte infohash of target torrent>"}
    response: {"id" : "<queried nodes id>", "token" :"<opaque write token>", "values" : ["<peer 1 info string>", "<peer 2 info string>"]}
    or: {"id" : "<queried nodes id>", "token" :"<opaque write token>", "nodes" : "<compact node info>"}

Example Packets:

    get_peers Query = {"t":"aa", "y":"q", "q":"get_peers", "a": {"id":"abcdefghij0123456789", "info_hash":"mnopqrstuvwxyz123456"}}
    bencoded = d1:ad2:id20:abcdefghij01234567899:info_hash20:mnopqrstuvwxyz123456e1:q9:get_peers1:t2:aa1:y1:qe
    Response with peers = {"t":"aa", "y":"r", "r": {"id":"abcdefghij0123456789", "token":"aoeusnth", "values": ["axje.u", "idhtnm"]}}
    bencoded = d1:rd2:id20:abcdefghij01234567895:token8:aoeusnth6:valuesl6:axje.u6:idhtnmee1:t2:aa1:y1:re
    Response with closest nodes = {"t":"aa", "y":"r", "r": {"id":"abcdefghij0123456789", "token":"aoeusnth", "nodes": "def456..."}}
    bencoded = d1:rd2:id20:abcdefghij01234567895:nodes9:def456...5:token8:aoeusnthe1:t2:aa1:y1:re


##4.4.announce_peer
这个请求用来表明发出 announce_peer 请求的节点，正在某个端口下载 torrent 文件。
announce_peer 包含 4 个参数。
第一个参数是 id，包含了请求节点的 ID；
第二个参数是 info_hash，包含了 torrent 文件的 infohash；
第三个参数是 port 包含了整型的端口号，表明 peer 在哪个端口下载；
第四个参数数是 token，这是在之前的 get_peers 请求中收到的回复中包含的。
收到 announce_peer 请求的节点必须检查这个 token 与之前我们回复给这个节点 get_peers 的 token 是否相同。
如果相同，那么被请求的节点将记录发送 announce_peer 节点的 IP 和请求中包含的 port 端口号在 peer 联系信息中对应的 infohash 下。

implied_port如果为1，port参数应该被忽略。UDP的端口作为peer的端口，用于NAT网络

    arguments:  {"id" : "<querying nodes id>",
      "implied_port": <0 or 1>,
      "info_hash" : "<20-byte infohash of target torrent>",
      "port" : <port number>,
      "token" : "<opaque token>"}
    
    response: {"id" : "<queried nodes id>"}
Example Packets:

    announce_peers Query = {"t":"aa", "y":"q", "q":"announce_peer", "a": {"id":"abcdefghij0123456789", "implied_port": 1, "info_hash":"mnopqrstuvwxyz123456", "port": 6881, "token": "aoeusnth"}}
    bencoded = d1:ad2:id20:abcdefghij012345678912:implied_porti1e9:info_hash20:mnopqrstuvwxyz1234564:porti6881e5:token8:aoeusnthe1:q13:announce_peer1:t2:aa1:y1:qe
    Response = {"t":"aa", "y":"r", "r": {"id":"mnopqrstuvwxyz123456"}}
    bencoded = d1:rd2:id20:mnopqrstuvwxyz123456e1:t2:aa1:y1:re