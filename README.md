# Solspace Freeform Payments plugin for Craft CMS 3.x

Freeform Payments allows you to easily collect payments and subscriptions from your site users, and is specifically built for expanding onto Freeform, the most powerful form building plugin for Craft CMS.

![Screenshot](src/icon.svg)

## Requirements

This plugin requires Craft CMS 3.0.0 or later, and either Freeform Lite or Pro 2.3.0 or later.

## Installation

To install Freeform, simply:

1. Go to the **Plugin Store** area inside your Craft control panel and search for *Freeform*.
2. Choose to install *Freeform Payments* by clicking on it (in addition to *Freeform Lite* and/or *Freeform Pro*, as Payments requires one or both to be installed).
3. Click on the **Try** button to install a trial copy of Freeform.
4. Try things out and if Freeform Payments is right for your site, and then purchase a copy of it through the Plugin Store when you're ready!

Freeform Payments can also be installed manually through Composer:

1. Open your terminal and go to your Craft project: `cd /path/to/project`
2. Then tell Composer to require the plugin: `composer require solspace/craft3-freeform-payments`
3. In the Craft control panel, go to *Settings → Plugins* and click the **Install** button for Freeform Payments.

## Freeform Payments Overview

Freeform Payments allows you to easily collect payments and subscriptions from your site users. It ties into either edition of Freeform (Lite or Pro, but one is required) and extends its functionality to include payment processing. Adding payment processing to forms in Freeform is so intuitive and simple to do. Within minutes you can have anything from a form accepting donations to a [membership registration form](https://solspace.com/craft/freeform/docs/user-registration-forms) that has users pay for a subscription at any interval level. Currently there is only an integration with Stripe, but we hope to have others in the future.

Freeform Payments accepts two different types of payments: *Single* one-time payments, and recurring *Subscriptions*. The necessary credit card fields will integrate seamlessly into your form and appear like the rest of your fields (and can be styled as such). For an even smoother feel for error handling and validation, try using AJAX with your form.

You are given full control to have a set payment option, allow users to fully customize their payment plan, and anything inbetween! Use regular Freeform fields for regular submission data (e.g. name, email address, etc) and even for setting payment amount, plan choices, interval choices, currency choices. Then use the Payments property editor to map your fields to Stripe accordingly.

Payments data can be displayed in templates and email notifications, as well as viewed inside the Freeform control panel when viewing list of submissions and single submission view. You can view the payment status, selected subscription plan and more. Users can self-cancel subscriptions (from Freeform generated email notifications) and admins can cancel subscriptions directly from the Freeform control panel. As subscriptions are cancelled, admins can view the auto-updated payment status directly inside Freeform as well.

It's important to note that Freeform does not store any sensitive credit card data, except for the last 4 digits of the credit card number.


## Using Freeform Payments

Full documentation for Freeform Payments can be found on the [Solspace website](https://solspace.com/craft/freeform/docs/payments).
