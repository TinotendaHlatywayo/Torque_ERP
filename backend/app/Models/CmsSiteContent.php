<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsSiteContent extends Model
{
    private const HERO_SLIDE_COUNT = 10;

    protected $fillable = [
        'key',
        'content',
        'published_content',
        'version',
        'published_version',
        'published_at',
        'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
        'published_content' => 'array',
        'published_at' => 'datetime',
        'version' => 'integer',
        'published_version' => 'integer',
    ];

    public static function defaults(): array
    {
        $defaults = [
            'brand_name' => 'Taziva Motor Spares',
            'logo_alt' => 'Taziva Motor Spares',
            'header_note' => 'Taziva Motor Spares · Genuine parts from our Rusape branch',
            'header_promo' => 'Pay on collection · Order online',
            'customer_login_label' => 'Customer login',
            'team_login_label' => 'Team sign in',
            'footer_about' => 'Browse genuine motor spares, reserve short-stock parts, and order online for collection from our Rusape branch.',
            'footer_copyright' => 'Taziva Motor Spares',
            'home_meta_title' => 'Taziva Motor Spares | Motor Spares & Workshop Support',
            'home_meta_description' => 'Browse genuine motor spares with photos, descriptions and prices, then order online or reserve stock.',
            'home_eyebrow' => 'TMS · Rusape',
            'home_heading' => 'The right part.',
            'home_heading_accent' => 'The right price.',
            'home_intro' => 'Browse motor spares with clear photos, descriptions and prices. Order online for collection or reserve short-stock parts in minutes.',
            'home_search_title' => 'What are you looking for?',
            'home_search_subtitle' => 'Search the live TMS branch catalogue.',
            'home_search_placeholder' => 'Try filters, brakes, spark plugs...',
            'home_shop_button' => 'Shop all spares',
            'home_account_button' => 'Create a customer account',
            'home_benefit_one_title' => 'Genuine branch stock',
            'home_benefit_one_body' => 'Every listing comes from the active TMS catalogue.',
            'home_benefit_two_title' => 'Collect locally',
            'home_benefit_two_body' => 'Order online and pay when you collect from Rusape.',
            'home_benefit_three_title' => 'Short-stock reservations',
            'home_benefit_three_body' => 'Hold selected parts for up to {minutes} minutes.',
            'home_browse_eyebrow' => 'Browse the catalogue',
            'home_browse_heading' => 'Shop by category',
            'home_featured_eyebrow' => 'Popular spares',
            'home_featured_heading' => 'Ready for the road',
            'about_meta_title' => 'About Us | Taziva Motor Spares',
            'about_meta_description' => 'Learn how Taziva Motor Spares supports drivers and workshops with motor spares and local service in Rusape.',
            'about_eyebrow' => 'About TMS',
            'about_heading' => 'Parts and workshop support you can count on.',
            'about_intro' => 'Taziva Motor Spares brings parts enquiries, online ordering and workshop support together in one place for drivers and fleets in Rusape and beyond.',
            'about_approach_eyebrow' => 'Our approach',
            'about_approach_heading' => 'Fitment first, price clearly.',
            'about_approach_body_one' => 'Tell us what vehicle you drive and what it needs. We help confirm the right part, show the current catalogue price, and give you a simple way to order or reserve.',
            'about_approach_body_two' => 'Our online store is connected to the same TMS stock system used by the branch, so customers see live product information instead of a static catalogue.',
            'about_next_heading' => 'Find the part you need.',
            'services_meta_title' => 'Services | Taziva Motor Spares',
            'services_meta_description' => 'Explore parts guidance, workshop support and online ordering services from Taziva Motor Spares in Rusape.',
            'services_eyebrow' => 'Our services',
            'services_heading' => 'Keep your vehicle moving.',
            'services_intro' => 'From a quick part enquiry to workshop planning, TMS helps you choose the right next step for your vehicle.',
            'service_one_title' => 'Brake checks',
            'service_one_body' => 'Ask about pad condition, disc wear and the right replacement set for your vehicle.',
            'service_two_title' => 'Routine servicing',
            'service_two_body' => 'Plan oil, filter and basic maintenance needs with guidance from the TMS team.',
            'service_three_title' => 'Diagnostics enquiries',
            'service_three_body' => 'Share symptoms or part numbers and get help narrowing down the likely cause.',
            'service_four_title' => 'Suspension and steering',
            'service_four_body' => 'Source shocks, mounts and related parts for stable handling on local roads.',
            'service_five_title' => 'Fitment support',
            'service_five_body' => 'Confirm vehicle details before you buy so you order the correct part the first time.',
            'service_six_title' => 'Online parts ordering',
            'service_six_body' => 'Browse published stock, add parts to your cart, and collect from the branch.',
            'services_enquiry_eyebrow' => 'Workshop enquiries',
            'services_enquiry_heading' => 'Need help choosing a part or planning a visit?',
            'services_enquiry_body' => 'Send your vehicle details, the part number or a description of the issue. The team can help confirm availability and the best way to proceed.',
            'contact_meta_title' => 'Contact Us | Taziva Motor Spares',
            'contact_meta_description' => 'Contact Taziva Motor Spares for fitment help, parts enquiries and workshop support in Rusape.',
            'contact_eyebrow' => 'Contact us',
            'contact_heading' => 'Tell us what you need.',
            'contact_intro' => 'Send a part number, vehicle detail or service question. We will help you find the right next step.',
            'contact_location_title' => 'Location',
            'contact_location_body' => '{branch}, Rusape, Zimbabwe. Confirm the exact shop address with the team.',
            'contact_hours_title' => 'Opening hours',
            'contact_hours_body' => "Contact the team to confirm today's hours and collection times.",
            'contact_phone_title' => 'Phone and WhatsApp',
            'contact_phone_body' => 'Use the enquiry form below and include the best number to reach you.',
            'contact_email_title' => 'Email',
            'contact_email_body' => 'No public contact email is configured yet. The form will prepare your message locally.',
            'contact_form_heading' => 'Parts and service enquiry',
            'contact_form_intro' => 'Add as much detail as you can: make, model, year, part number or a photo reference.',
            'products_meta_title' => 'Motor Spares | Taziva Motor Spares',
            'products_meta_description' => 'Browse published motor spares with live branch prices and images.',
            'products_heading' => 'Motor spares in stock',
            'products_intro' => 'Browse the live TMS catalogue and order for collection.',
            'cart_meta_title' => 'Your Cart | Taziva Motor Spares',
            'cart_meta_description' => 'Review your motor spares order before collection.',
            'cart_heading' => 'Your cart',
            'account_meta_title' => 'My Account | Taziva Motor Spares',
            'account_meta_description' => 'View your online orders and reservations.',
            'account_heading' => 'My account',
            'login_meta_title' => 'Team Sign In | TMS',
            'login_meta_description' => 'Sign in to your TMS customer account, branch workspace or platform.',
            'signup_meta_title' => 'Create Account | Taziva Motor Spares',
            'signup_meta_description' => 'Create a TMS customer account to order online and track reservations.',
            'logo_media_id' => null,
            'favicon_media_id' => null,
            'home_hero_media_id' => null,
            'about_media_id' => null,
            'services_media_id' => null,
            'contact_media_id' => null,
            'home_hero_image_alt' => 'Brake pads ready for collection',
            'about_image_alt' => 'Taziva Motor Spares team and workshop',
            'services_image_alt' => 'Motor spares and workshop support',
            'contact_image_alt' => 'Contact Taziva Motor Spares',
        ];

        $slideParts = ['brake pads', 'an air filter', 'an oil filter', 'spark plugs', 'a shock absorber', 'an alternator', 'a car battery', 'a brake disc', 'a fuel filter', 'a radiator'];

        for ($slide = 2; $slide <= self::HERO_SLIDE_COUNT; $slide++) {
            $defaults["home_hero_slide_{$slide}_media_id"] = null;
            $defaults["home_hero_slide_{$slide}_image_alt"] = 'Photo of '.$slideParts[$slide - 2].' sold by Taziva Motor Spares';
        }

        return $defaults;
    }

    public static function mediaKeys(): array
    {
        $keys = ['logo_media_id', 'favicon_media_id', 'home_hero_media_id', 'about_media_id', 'services_media_id', 'contact_media_id'];

        for ($slide = 2; $slide <= self::HERO_SLIDE_COUNT; $slide++) {
            $keys[] = "home_hero_slide_{$slide}_media_id";
        }

        return $keys;
    }

    public static function imageAltKeys(): array
    {
        $keys = ['logo_alt', 'home_hero_image_alt', 'about_image_alt', 'services_image_alt', 'contact_image_alt'];

        for ($slide = 2; $slide <= self::HERO_SLIDE_COUNT; $slide++) {
            $keys[] = "home_hero_slide_{$slide}_image_alt";
        }

        return $keys;
    }

    public static function imageAltKey(string $mediaKey): string
    {
        if (str_starts_with($mediaKey, 'home_hero_slide_') && str_ends_with($mediaKey, '_media_id')) {
            return str_replace('_media_id', '_image_alt', $mediaKey);
        }

        return match ($mediaKey) {
            'favicon_media_id', 'logo_media_id' => 'logo_alt',
            'home_hero_media_id' => 'home_hero_image_alt',
            'about_media_id' => 'about_image_alt',
            'services_media_id' => 'services_image_alt',
            'contact_media_id' => 'contact_image_alt',
            default => 'logo_alt',
        };
    }

    public static function imageDefaults(): array
    {
        $defaults = self::defaults();
        $images = [
            'logo_media_id' => ['url' => '/tms_logo_transparent.png', 'alt' => (string) $defaults['logo_alt']],
            'favicon_media_id' => ['url' => '/tms_logo_transparent.png', 'alt' => (string) $defaults['logo_alt']],
            'about_media_id' => ['url' => '/images/site/about.jpg', 'alt' => (string) $defaults['about_image_alt']],
            'services_media_id' => ['url' => '/images/site/services.jpg', 'alt' => (string) $defaults['services_image_alt']],
            'contact_media_id' => ['url' => '/images/site/contact.jpg', 'alt' => (string) $defaults['contact_image_alt']],
        ];

        for ($slide = 1; $slide <= self::HERO_SLIDE_COUNT; $slide++) {
            $mediaKey = $slide === 1 ? 'home_hero_media_id' : "home_hero_slide_{$slide}_media_id";
            $altKey = $slide === 1 ? 'home_hero_image_alt' : "home_hero_slide_{$slide}_image_alt";
            $images[$mediaKey] = [
                'url' => sprintf('/images/site/hero-slide-%02d.jpg', $slide),
                'alt' => (string) $defaults[$altKey],
            ];
        }

        return $images;
    }

    public function draftContent(): array
    {
        return array_replace(self::defaults(), $this->content ?? []);
    }

    public function publicContent(): array
    {
        return array_replace(self::defaults(), $this->published_content ?? []);
    }
}
