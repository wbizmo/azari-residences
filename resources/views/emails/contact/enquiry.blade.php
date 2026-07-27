@include('emails.premium', [
    'title' => 'New guest enquiry',
    'preheader' => 'A new message was submitted through the Azari website.',
    'lines' => [
        'Name: '.$enquiry['name'],
        'Email: '.$enquiry['email'],
        'Phone: '.($enquiry['phone'] ?: 'Not supplied'),
        'Subject: '.$enquiry['subject'],
        $enquiry['message'],
    ],
    'actionLabel' => 'Reply to guest',
    'actionUrl' => 'mailto:'.$enquiry['email'],
])
