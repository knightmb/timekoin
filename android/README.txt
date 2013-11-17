-=================== OVERVIEW =====================-

Timekoin is an open source peer to peer based crypto-currency system. As such,
it relies on a combination of its own database and the database of other peers
to maintain one large public register. Because this public register can be seen
by anyone, the only way to make sure that one peer doesn't tamper with the
balance of another peer is to use key based encryption.

A peer has two keys. One to create transactions and another to unlock what is
inside the transaction. When a transaction is created, the key to unlock it is
also passed along with the transaction data. This allows anyone to look inside
the data to verify the contents, but prevents anyone from trying to masquerade
as that peer and create a transaction that is fake or changed. Any attempts to
alter the transaction data will fail because the key provided with it will no
longer work.

Timekoin uses 1,536 bit encryption to create the transactions and
SHA256 hashing both inside and outside the transaction to provide multiple
layers of anti-tampering protection wrapped in each other. Trying to tamper with
any of the 3 points results in the collapse of the entire transaction data.

The software requires these components before installing.

1. Web Server (Apache, IIS, etc.)
2. PHP (v5 or higher) for the Web Server, including the mySQL, GMP, and
*OpenSSL* module if possible
3. mySQL or MariaDB Database Server (v5 or higher, free community version works just fine)

-=================== QUICK INSTALL =====================-

If you are a computer guru, the software can be setup on any operating system
that supports the requirements above (Windows, Mac, Linux, Unix, etc.) If you already
have or know how to quickly install these, then you will be able to get up and
running very quickly.




That concludes this quick install section. For more in depth information (walk
through installations) visit the website for more information about Timekoin. 
There you will find more resources and forums to get the maximum enjoyment out
of your Timekoin client software.

http://timekoin.org/


Sincerely!
The Timekoin Community
