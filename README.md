1. Clone the Repository
 # git clone https://github.com/your-repo/supermarket-checkout.git
cd supermarket-checkout
2. Install Dependencies
Run the following command to install PHP dependencies using Sail:

# ./vendor/bin/sail composer install

3. Start the Sail

# ./vendor/bin/sail up -d

5. Run Migrations

# ./vendor/bin/sail artisan migrate

6. Seed the Database

# ./vendor/bin/sail artisan db:seed

7. Test the Application

# http://localhost/checkout?products=FR1,SR1,FR1,CF1

Response:

{
    "product_codes": ["FR1", "SR1", "FR1", "CF1"],
    "total": 22.45
}

8. Stop App 

# ./vendor/bin/sail down