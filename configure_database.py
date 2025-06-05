def get_database_credentials():
    """Prompts the user for database credentials and returns them."""
    host = input("Enter the database host: ")
    username = input("Enter the database username: ")
    password = input("Enter the database password: ")
    database_name = input("Enter the database name: ")

    return {
        "host": host,
        "username": username,
        "password": password,
        "database_name": database_name,
    }

if __name__ == "__main__":
    print("Please provide your database credentials.")
    credentials = get_database_credentials()
    print("\nCollected database credentials:")
    for key, value in credentials.items():
        print(f"{key.replace('_', ' ').capitalize()}: {value}")

    print("\nNote: In a real application, these credentials would be securely stored")
    print("in a configuration file or environment variables, not just printed.")
