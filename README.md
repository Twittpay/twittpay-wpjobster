# TwittPay for WPJobster

WPJobster payment gateway

Part of the [TwittPay](https://twittpay.com) addon family.

## Quick start

1. Download the latest zip from the **Releases** page of this repository.
2. Install it on your WPJobster following the guide below.
3. Open the TwittPay settings and enter your **Brand Key**. You can get it from [your dashboard](https://twittpay.com/user/brands).
4. Make a small test payment to confirm everything works.

Payments are always re-verified on your server before an order or invoice is marked paid.

## Detailed installation guide

```text
===========================================================================
 TwittPay - WPJobster payment gateway
===========================================================================

 This package holds only the new gateway folder. One line in your theme must
 be added. Back the file up first.

 1. COPY THE FOLDER
      wp-content/themes/wpjobster/lib/plugins/wpjobster-twittpay/

 2. wp-content/themes/wpjobster/lib/init.php
      In the list of gateway plugin files, add one entry the same way the
      other gateways are listed:
        'wpjobster-twittpay/wpjobster-twittpay.php',

 3. WPJobster admin > Payment Gateways > TwittPay
      Paste your Brand Key, enable the gateway.

 NOTES
   * The checkout address is built in. Only the Brand Key is needed.
   * TwittPay takes BDT. The gateway is limited to BDT.
   * Each payment is verified on your server, must match its payment record
     and the amount, and an order is completed only once.
   * Make one small test payment after installing.
```
