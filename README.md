# Soko Suite

A storefront, M-Pesa checkout and business desk for WordPress, built for Kenyan businesses that sell products and installations.

**Dynamic Desk** (at `/desk`) is the back office: staff sign in there and never need wp-admin.

## What it includes

- **Storefront** (theme-independent): home page built from editable sections, shop with categories and filters, product pages, Power Planner, cart, checkout with M-Pesa, order tracking and payment links.
- **Sales**: quotations, invoices (with deposits), receipts, PDFs, email and WhatsApp sharing.
- **Payments**: M-Pesa Express (STK push) and C2B Paybill/Till confirmations through Safaricom Daraja, automatic matching by invoice number, manual bank and cash payments.
- **CRM**: leads pipeline (website quote form and Power Planner feed it) and customer history.
- **Operations**: installation jobs with checklists, schedule with reminders, products and stock, purchase orders and suppliers.
- **Money**: expenses with receipt photos, reports (profit and loss, cash flow, sales, receivables, VAT, stock, payments, expenses, installations) exported to Excel, PDF or CSV, scheduled email reports.
- **Compliance**: KRA eTIMS tracking, VAT summary, licence and permit renewals.
- **Site Studio**: edit pages, sections, menu, brand colours and fonts, projects and footer with a live preview.
- **Team and access**: roles for owner, manager, accountant, sales, technician, store keeper and website editor.

## Install

1. Upload the plugin zip in wp-admin → Plugins → Add New → Upload, or install from this repository.
2. Activate. Go to Settings → Permalinks and save once.
3. Open `/desk/`, then Settings to add business details, M-Pesa Daraja keys and delivery options.

Updates install automatically from the `main` branch of this repository. Desk → Settings → System → **Update now** pulls the latest version immediately.

## M-Pesa

Add the shortcode, consumer key, consumer secret and passkey from the Daraja portal in Desk → Settings → Payments, then press **Register payment notifications with Safaricom**. Customers paying to the Paybill use the invoice number as the account number and payments match automatically.

## Requirements

WordPress 6.2+, PHP 7.4+, the PHP `zip` extension for Excel exports. No Composer packages. PDFs use the bundled FPDF library (see `lib/fpdf/license.txt`).
