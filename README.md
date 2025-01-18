
# **Laravel Supermarket Checkout**

This application manages products, pricing rules, and calculates the total price of scanned items using various discount rules.

---

## **Setup Instructions**

### **1. Clone the Repository**
```
git clone https://github.com/your-repo/supermarket-checkout.git
cd supermarket-checkout
```

---

### **2. Install Dependencies**
Run the following command to install PHP dependencies using Sail:
```
./vendor/bin/sail composer install
```

---

### **3. Start Sail**
Bring up the Sail containers:
```
./vendor/bin/sail up -d
```

---

### **4. Run Migrations**
Run database migrations to create the necessary tables:
```
./vendor/bin/sail artisan migrate
```

---

### **5. Seed the Database**
Seed the database with initial product and pricing rules data:
```
./vendor/bin/sail artisan db:seed
```

---

### **6. Test the Application**

#### **Checkout Endpoint**
**URL**:
```
http://localhost/checkout?products=FR1,SR1,FR1,CF1
```

**Query Parameter**:
- `products`: Comma-separated list of product codes.

**Example**:
```
GET http://localhost/checkout?products=FR1,SR1,FR1,CF1
```

**Response**:
```
{
    "product_codes": ["FR1", "SR1", "FR1", "CF1"],
    "total": 22.45
}
```

---

### **7. Stop the Application**
To stop Sail containers:
```
./vendor/bin/sail down
```

---

### **Summary**
This README provides all the steps to set up, run, and test the Laravel Supermarket Checkout application. If you encounter any issues, ensure your `.env` file is correctly configured and that the Sail containers are running.
