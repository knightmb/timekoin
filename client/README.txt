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

All requirements above can use default settings, no special tweaking needed as
far as we know.

1.  Checklist Requirements:

	A. Verify that your web server has installed, PHP5 (with mySQL, GMP,
*OpenSSL* if possible) modules installed, and that you have access to
your mySQL server to create new databases, set user permissions, etc. It's also
important that you have the ability to modify access rights on your web server
as some of the software will need execution privileges to function properly.


2. Database Setup:

	A. Start by creating a fresh database in mySQL, the default Collation of
should be fine, but if can set it for “utf8_general_ci” that is even better.
What you name the database is your business, but to be consistent we will name
our database “tk_client_db” in this document.

	B. Create a new user account in mySQL that will give you access to this
new database. The user account needs only very limited privileges to function,
so there is no need to give your new user account more permissions that it needs
to run the software. The privileges of SELECT, INSERT, UPDATE, DELETE, and DROP
is all the account needs. In this example, we created a new user account and
named it “tk_client” (how original). Be sure to set a good password for this
account.


3. File Setup on Web Server

	A. Now that the database is prepared, begin by creating a folder on your
web server. The folder name can be whatever you like, but we recommend
“tkclient” for these instructions. Copy all the files from the v1.X folder to
this new web folder. Make sure your directory structure stays intact so that the
“css” and “img” folders make it properly to their new home.

	B. File permissions are important as some files will need special
permissions to run properly (depends on if the setup is for Linux, Windows, Mac,
etc.) All files need at least “read” access, but this next list of files need
execute permissions to allow them to run with the web server permissions (no
admin or root needed). For those using Linux/Unix, apache permissions would be
an example. Every OS is different, so some may work with just
read access and others may require execute access to function properly depending
on the security settings chosen by the user of the OS.

--index.php (read / execute)
--task.php (read / execute)


If you plan on doing manual updates in the future, then all other files can
remain at read access only.

If you prefer to use the auto-update feature, then all files will need
read / write access to allow the software to update itself.

	C. All other files can remain at read access only. If you want to see if
PHP is executing your scripts properly, you can already visit the login page of
“index.php” Even though no database is setup yet, you will at least see the page
if it renders properly.

	D. Next, you need to modify the “configuration.php” file and fill out
the 4 fields. They include the MYSQL_IP which if your mySQL server runs
(localhost or some other IP address). Next is the MYSQL_USERNAME which you
created earlier, followed by MYSQL_PASSWORD which is self explanatory. Finally,
MYSQL_DATABASE which is the name of the database you created. Save this file
after modifications are complete.


4. Database New Install Information

	A. You will find in the “new_install_sql” folder one single file labeled
“tkclient.sql”. This contains a complete layout of a fresh new timekoin client
database. You can simply import it directly into your new Timekoin database
either via command line or from a web gui (like phpMyAdmin for example). This
will create all the tables and data you need to continue on to the next steps of
installation.


5. Login to new Client

	A. At this point, you are nearly finished. All that remains is to login
and change some custom options before using your timekoin client for the
first time. Simple visit the “index.php” in your web browser and use this combo
to login with (as it's the default with a new install).
Username: timekoin
Password: 12345

	B. After login, click on the “Options” tab and change the username and
password so something better than the default for security reasons. The username
and password are stored as an encrypted hash, so there is no way to recover a
password or username if lost. Be sure to write down or save what you intend to
use for this.

	C. Key/Pair Creation for your Timekoin Client is next. Simply go to the
"Options" tab and you should see a button to "Generate New Keys". This will
generate a new random Private and Public key pair.


That concludes this quick install section. For more in depth information (walk
through installations) visit the website for more information about Timekoin. 
There you will find more resources and forums to get the maximum enjoyment out
of your Timekoin client software.

http://timekoin.org/


Sincerely!
The Timekoin Community
