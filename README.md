# Akeneo PIM OroCommerce Connector

## Short overview
This extension allows you to connect [OroCommerce Enterprise](https://oroinc.com/b2b-ecommerce/) with [Akeneo PIM Enterprise](https://www.akeneo.com/) and use the latter’s rich capabilities for product information management with your OroCommerce-powered web store. Combine personalized B2B buying experience with compelling product experience to maximize your content marketing ROI and stay ahead of the B2B eCommerce game.

## Description

Akeneo is a Product Information Management (PIM) solution that provides a single place to collect, manage, and enrich your product information, create a product catalog, and distribute it to your sales and eCommerce channels. In integration with OroCommerce, Akeneo makes it faster and easier to create and deliver compelling product experiences to your online customers.

With this extension, you will be able to sync the following data from Akeneo to OroCommerce:

* Attributes and attribute options
* Attribute families and attribute family groups
* Configurable products, simple products & all product data from supported attributes
* Categories and category trees

## Installation

1. Add composer package

```
composer require "oro/commerce-akeneo:1.6.*"
```

2. Follow [Installation Guide](https://oroinc.com/b2b-ecommerce/doc/1.6/install-upgrade)

3. Configure [Message Queue](https://oroinc.com/b2b-ecommerce/doc/1.6/admin-guide/op-structure/mq)

** Recommended time limit option values is 30 seconds `--time-limit=+30seconds`

## Setting up the integration

Create a new integration to start synchronizing data from Akeneo to OroCommerce. 

In OroCommerce, go to "System -> Integrations -> Manage integrations" and click "Create Integration". Select "Akeneo" as the integration type.

"User ClientId" and "User Secret" can be created in Akeneo at "System > API connections."

After entering the URL and authentication details, press the "Check Akeneo Connection" button. When everything configured correctly, we will retrieve the available channels, locales, and currencies from Akeneo. In case of an error, you will see more information about what went wrong.

If the connection is successful, continue entering the rest of the information. In case you do not want to sync all products, use [filters](https://api.akeneo.com/documentation/filter.html).

Start by pressing the "Schedule Sync" button. Once the sync is complete, ensure to update the schema. Refresh the integration update page and press the "Update Schema" button. Once the schema update is complete, you can schedule another sync. You can schedule full sync now by pressing the "Schedule Sync" button one more time.

## Limitations

Because of the differences between Akeneo and OroCommerce, you should take into account a few limitations.

* When you create new attributes on the Akeneo side, they won't be saved to the product information in OroCommerce until the schema has been updated manually by pressing the "Update Schema" button on the integration page.
* When you add a select or multi-select attribute in Akeneo, the options won't be synchronized until the schema has been updated.
* Akeneo date field type can have a different value for every locale. The same behavior is not possible in OroCommerce. For this reason, it is imported as a localized fallback value.
* Akeneo multi-select fields can have different values per locale. In OroCommerce, all the values from the various locales are combined.
* Akeneo select fields can have different values per locale. In OroCommerce, the value from the default locale is used.
* In OroCommerce. the Akeneo image field type is saved not as an attribute, but as a product image.
* In OroCommerce, products are validated which can cause some products to be skipped during import because the field requirements are different in Akeneo. Check the details in an integration status. For example:
* SKUs in Akeneo can contain spaces. In OroCommerce, the spaces are unacceptable. 
* An attribute family is not required in Akeneo. In OroCommerce, an attribute family is always required.
* OroCommerce does not support multiple categories, only the first category listed is used for product assignment.
* OroCommerce does not support prices per currency, only the last price per currency listed is used for product assignment.
* Categories tree rebuild starts after categories import.
* Optional listeners are disabled by default. Please reindex data and recalculate prices and slugs manually.
