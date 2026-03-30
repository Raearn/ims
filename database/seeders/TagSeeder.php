<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            'DDoS', 'Server Error', 'Phishing', 'Malware', 'Hardware Failure',
            'Network Outage', 'Software Bug', 'Payment Issue', 'Login Problem', 'Email Bounce',
            'Data Loss', 'Database Corruption', 'API Timeout', 'Security Breach', 'UI Glitch',
            'Deployment Error', 'Integration Issue', 'Account Lockout', 'Storage Full', 'High Latency',
            'SSL Expired', 'DNS Issue', 'Domain Transfer', 'Password Reset', '2FA Failure',
            'Spam', 'Feature Request', 'Configuration Error', 'Patch Revert', 'Mobile App Crash',
            'Performance Issue', 'Report Generation Failed', 'Billing Dispute', 'Missing Features', 'Access Denied',
            'Browser Incompatibility', 'Cache Issue', 'Scheduled Maintenance', 'Slow Query', 'Form Validation',
            'Image Upload', 'Export Error', 'Broken Link', 'Out of Memory', 'Unresponsive',
            'Typo', 'Video Playback', 'Third-party Outage', 'Webhook Failed', 'Zero-day Exploit',
        ];

        foreach ($tags as $tagName) {
            Tag::firstOrCreate(['name' => $tagName]);
        }
    }
}
