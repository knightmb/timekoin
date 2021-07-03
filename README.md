Timekoin is a long tested set of protocol rules and software implementation for an open encrypted electronic currency system on a public network. To date, there has been No Hack or Exploit of the Timekoin network implementation for open p2p digital currency. Timekoin is not a clone of other existing digital currency systems (such as Bitcoin, Litecoin, etc). Timekoin is a very different and unique way to secure transactions and create currency. Timekoin uses customized RSA encryption and SHA-256 hashing to secure transactions. Transactions work like clockwork, being processed every 5 minutes. Transaction processing is not dependent on Currency creation and this makes it unique among all other popular digital currencies. This separation of the two systems allows each to function independently of the other. The design of Timekoin allows it to function on computer systems or devices (such as the Raspberry Pi) with minimal effort needed to maintaining security and speed.

Economy:
1. The Timekoin economy runs on very simple rules. These rules are enforced by all peers participating in the network.
2. No hard limit on currency creation. There is no cap of the maximum amount of currency that will ever be created.
3. All Timekoins are whole numbers. No Decimal point currency is used to avoid rounding and accuracy errors.
4. Transactions cost nothing except the CPU time it took your computer to encrypt the transaction.
5. No Double-Spending a balance for obvious reasons.
6. No Spending to yourself. Transactions such as this are ignored.
7. 100 Transaction queue limit. Each public key may only queue 100 transactions to be processed by the network for each 5 minute transaction cycle. This insures the network is not flooded with bogus transactions.
8. Currency Generation and Currency Generation Election Request are restricted to a single IP address basis to combat network flooding.
9. Any peer can generate currency, but must be elected by the network peers first. There is also a network fee to request election by the network. The fee is the number of generating servers total paid to each unique public key before being considered for peer election. The election process is random to give each peer an equal chance of winning.
10. Elected peers are allowed to create currency so long as they remain online and generate at least 1 unit of currency every 8 hours, otherwise the peer loses the elected status and must be elected again to generate currency.
11. Elections are chosen at random times in the future based on the forward movement of time and seeded by the Transaction History of the Timekoin network.
12. Generating currency (for elected peers) is chosen at random times in the future based on the forward movement of time and seeded by the Transaction History of the Timekoin network. This organic randomness makes it nearly impossible to predict far into the future.

Features:
1. Open Source software, can run on any operating system that can run a Web server, PHP5 and a Database server.
2. Any modern web browser can manage the system (be it tablet, phone, or desktop PC)
3. Very low resource footprint. You can resurrect old machines and give them new life as a Timekoin server.
4. Everything is laid out for view in the program GUI. Nothing is hidden from the user, you can watch/monitor every aspect of the Timekoin network.

The software requires these components before installing.
1. Web Server (Apache, IIS, etc.)
2. PHP5 for the Web Server, including the CLI, mySQLi, GMP
extensions (OpenSSL optional but recommended for speed)
3. mySQL (or drop in MariaDB) Database Server (v5 or higher, free community 
version works just fine)

Learn More at https://timekoin.net/
