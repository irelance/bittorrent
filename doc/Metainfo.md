#1. Metainfo
就是.torrent文件中包含的信息

UTF-8编码
```json
{
  "announce":"tracker的url",
  "info":{
    "name":"",
    "piece length":"",
    "pieces":"",
    "length":"单文件才有",
    "files":[{//多文件才有
      "path":["path","to","file"],
      "length":""
    }],
    "private":1 //PT
  },
  "announce-list": [ ["tracker1"], ["backup1"], ["backup2"] ]//如果"announce-list"存在，客户端将忽略"announce"，官方推荐策略是每次循环只请求1成功次tracker
}
```
    
- 一个无 tracker 的 torrent 文件字典不包含 announce 关键字，而使用一个 nodes 关键字来替代。这个关键字对应的内容应该设置为 torrent 创建者的路由表中 K 个最接近的节点。可供选择的，这个关键字也可以设置为一个已知的可用节点，比如这个 torrent 文件的创建者。请不要自动加入 router.bittorrent.com 到 torrent 文件中或者自动加入这个节点到客户端路由表中。
