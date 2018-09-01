#1. Connect
Before announcing or scraping, you have to obtain a connection ID.

Choose a random transaction ID.
Fill the connect request structure.
Send the packet.

connect request:

    Offset  Size            Name            Value
    0       64-bit integer  protocol_id     0x41727101980 // magic constant
    8       32-bit integer  action          0 // connect
    12      32-bit integer  transaction_id
    16
Receive the packet.
Check whether the packet is at least 16 bytes.
Check whether the transaction ID is equal to the one you chose.
Check whether the action is connect.
Store the connection ID for future use.

connect response:

    Offset  Size            Name            Value
    0       32-bit integer  action          0 // connect
    4       32-bit integer  transaction_id
    8       64-bit integer  connection_id
    16

#2. Announce
Choose a random transaction ID.
Fill the announce request structure.
Send the packet.

IPv4 announce request:

    Offset  Size    Name    Value
    0       64-bit integer  connection_id
    8       32-bit integer  action          1 // announce
    12      32-bit integer  transaction_id
    16      20-byte string  info_hash
    36      20-byte string  peer_id
    56      64-bit integer  downloaded
    64      64-bit integer  left
    72      64-bit integer  uploaded
    80      32-bit integer  event           0 // 0: none; 1: completed; 2: started; 3: stopped
    84      32-bit integer  IP address      0 // default
    88      32-bit integer  key
    92      32-bit integer  num_want        -1 // default
    96      16-bit integer  port
    98
Receive the packet.
Check whether the packet is at least 20 bytes.
Check whether the transaction ID is equal to the one you chose.
Check whether the action is announce.
Do not announce again until interval seconds have passed or an event has occurred.
Do note that most trackers will only honor the IP address field under limited circumstances.

IPv4 announce response:

    Offset      Size            Name            Value
    0           32-bit integer  action          1 // announce
    4           32-bit integer  transaction_id
    8           32-bit integer  interval
    12          32-bit integer  leechers
    16          32-bit integer  seeders
    20 + 6 * n  32-bit integer  IP address
    24 + 6 * n  16-bit integer  TCP port
    20 + 6 * N
    
#3. Errors
error response:

    Offset  Size            Name            Value
    0       32-bit integer  action          3 // error
    4       32-bit integer  transaction_id
    8       string  message