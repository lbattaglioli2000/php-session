# php-session
> A simple database-driven PHP session driver

## The Problem with Sessions

The biggest problem that PHP sessions present are their unique session IDs. Their unique IDs effectively bust the cache, and cause every session to become uncached. This will cause serious performance issues for your site. With that in mind, WP Engine specifically ignores HTTP headers that define a `PHPSESSID` cookie.

PHP Sessions also store data to the filesystem as their own unique temp file. Writing data to a file is an I/O process, which are known to back up and cause high server load. This kind of session storage also simply doesn’t work if your site is on an AWS clustered solution spanning multiple web servers.

Finally, there are multiple security vulnerabilities centering around PHP Sessions. Vulnerabilities include session data being exposed, session fixation, and session hijacking.

**All that being said, sometimes the solution you're trying to build just needs to use a damn session.**

## Enter the `Session` Class

This Session class can be added into any WordPress theme or plugin, and it gives you a simple little Session object to interact with a database-backed sessions.

> This class uses constructor property promotion, and therefore does require **PHP 8** or higher.

### Features

- Encrypt and decrypt session data to ensure it's secure while at rest and in transit.
- Retrieving a Session by its unique ID
- Get, set, or delete values from the session data
- Automatically sets a Session ID cookie
- Offers handy helper methods to:
  - Access when the Session will expires at
  - Access when the Session was created at
  - Check if the Session has a given key
 
### Installation

Start off by simply adding the class file into your theme or plugin. I like to write any custom functionality in the form of a plugin so this README will assume you're also building a plugin, but it can be adapted to any theme too by updating the `functions.php` file.

Inside of the `Session.php` file, you should probablly generate a new encryption key to keep things as secure as possible. Simply update the `ENCRYPTION_KEY` constant to a new value:

```php
private const ENCRYPTION_KEY = "YOUR_ENCRYPTION_KEY_GOES_HERE";
```

Then, somewhere in your plugin you should include the `Session.php` file:

```php
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Session.php';
```

This class does also need a database table to track the sessions. You should call this function when you're activating your plugin:

```php
function setup_tables(): void
{
    // check if the sessions table exists and create it if it doesn't
    global $wpdb;
    $table_name = $wpdb->prefix . 'db_backed_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        session_data longtext NOT NULL,
        session_expiry datetime NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY id (id)
    ) $charset_collate;";

    // run the query
    $results = $wpdb->query($sql);
    
    if ($results === false) {
        error_log('Error creating database backed sessions table');
    }
}
```

Once your table is setup, and you've included the Session class, you can start using it as described in the Usage section.

### Usage

The Session class has the following methods:

#### Static Methods

`Session::start()`
- Generates a new unique Session ID, and stores is as a cookie.
- Creates a new empty session data entry in the db_backed_sessions table, with the corresponding Session ID
- Returns a new empty Session instance

`Session::destroy(string $session)`
- Deletes the session matching the provided Session ID from the db_backed_sessions table

`Session::generate_session_id()`
- Returns a unique new 32 character session ID, to identify each unique session

`Session::retrieve(string $session)`
- Returns a Session instance back, populated with the data that matches the specified Session ID

`Session::update(string $session, array $data)`
- Allows you to completely overwrite the session data for the specified Session ID

#### Instance Methods
Once you've gotten a Session instance back, from either:

- Creating a new Session with `Session::start()`
- Or retrieving an existing Session with `Session::retrieve($sessionId)`

You're able to use the following methods to interact with the data stored within the session.

- `$session->get(string $key)`: Returns the value from the data matching the given key
- `$session->set(string $key, mixed $value)`: Sets the key to the provided value
- `$session->has(string $key)`: Checks if the session has the given key
- `$session->delete(string $key)`: Deletes the given key from the data
- `$session->save()`: Persists the updated session data to the database
- `$session->expiresAt()`: Retrieves when the session expires at
- `$session->createdAt()`: Retrieves when the session was created at
