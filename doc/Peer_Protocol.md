#1. Peer Protocol
BitTorrent's peer protocol operates over TCP or uTP

#2. handshake
官方扩展

    reserved[5]
    0x10  LTEP (Libtorrent Extension Protocol)

    reserved[7]
    0x01  BitTorrent DHT
    0x04  suggest, haveall, havenone, reject request, and allow fast extensions

非官方扩展

    reserved[0]
    0xFF  BitComet Extension Protocol
    0x80  Azureus Messaging Protocol
    
    reserved[1]
    0xFF  BitComet Extension Protocol
    
    reserved[2]
    0x08  BitTorrent Location-aware Protocol (no known implementations)
    
    reserved[5]
    0x02  Extension Negotiation Protocol
    0x01  Extension Negotiation Protocol
    
    reserved[7]
    0x01  XBT Metadata Exchange (implemented only in XBT)
    0x02  XBT Peer Exchange
    0x08  NAT Traversal
    0x10  hybrid torrent legacy to v2 upgrade

#3. peer messages
##3.1. Core Protocol:

    0x00   choke
    0x01   unchoke
    0x02   interested
    0x03   not interested
    0x04   have
    0x05   bitfield
    0x06   request
    0x07   piece
    0x08   cancel

##3.2. DHT Extension:
handshake的reserved[7] |= 0x01

    0x09   port

##3.3. Fast Extensions:
handshake的reserved[7] |= 0x04

    0x0D   suggest
    0x0E   have all
    0x0F   have none
    0x10   reject request
    0x11   allowed fast

##3.4. Extension Protocol
handshake的reserved[5] |= 0x10

    0x14    extended
    
##3.5. Hash Transfer Protocol:
v2新增内容

    0x15   hash request
    0x16   hashes
    0x17   hash reject
    

- peers 如果支持 DHT 协议就将 BitTorrent 协议握手消息的保留位的第 8 字节的最后一位置为 1。这时如果 peer 收到一个 handshake 表明对方支持 DHT 协议，就应该发送 PORT 消息。它由字节 0x09 开始，payload 的长度是 2 个字节，包含了这个 peer 的 DHT 服务使用的网络字节序的 UDP 端口号。当 peer 收到这样的消息是应当向对方的 IP 和消息中指定的端口号的节点发送 ping。如果收到了 ping 的回复，那么应当使用上述的方法将新节点的联系信息加入到路由表中。
 

```handshake``` -> ```handshake```, ```bitfield```, ```port```, ```have```, ...

```interested``` -> ```unchoke```

```not interested``` -> ```choke```

```request``` -> ```piece``` or ```reject request```

#4. peer messages 非官方扩展
##4.1. Additional IDs used in deployed clients:

    0x14   LTEP Handshake (implemented in libtorrent, uTorrent,...)