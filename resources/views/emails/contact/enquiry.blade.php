<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>New Azari website enquiry</title>
</head>

<body
    style="
        margin: 0;
        padding: 32px 16px;
        background: #f5efe3;
        color: #09271f;
        font-family: Arial, Helvetica, sans-serif;
    "
>
    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
    >
        <tr>
            <td align="center">
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        max-width: 640px;
                        overflow: hidden;
                        border: 1px solid #ded5c7;
                        border-radius: 18px;
                        background: #fffdf8;
                    "
                >
                    <tr>
                        <td
                            style="
                                padding: 28px 32px;
                                background: #09271f;
                                color: #ffffff;
                            "
                        >
                            <div
                                style="
                                    margin-bottom: 8px;
                                    color: #d5b16c;
                                    font-size: 11px;
                                    font-weight: 700;
                                    letter-spacing: 2px;
                                    text-transform: uppercase;
                                "
                            >
                                Website contact form
                            </div>

                            <h1
                                style="
                                    margin: 0;
                                    font-family: Georgia, serif;
                                    font-size: 30px;
                                    font-weight: 500;
                                "
                            >
                                New guest enquiry
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px">
                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                            >
                                <tr>
                                    <td
                                        style="
                                            width: 120px;
                                            padding: 0 16px 16px 0;
                                            color: #756d62;
                                            font-size: 12px;
                                            font-weight: 700;
                                            text-transform: uppercase;
                                            vertical-align: top;
                                        "
                                    >
                                        Name
                                    </td>

                                    <td
                                        style="
                                            padding: 0 0 16px;
                                            color: #09271f;
                                            vertical-align: top;
                                        "
                                    >
                                        {{ $enquiry['name'] }}
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding: 0 16px 16px 0;
                                            color: #756d62;
                                            font-size: 12px;
                                            font-weight: 700;
                                            text-transform: uppercase;
                                            vertical-align: top;
                                        "
                                    >
                                        Email
                                    </td>

                                    <td
                                        style="
                                            padding: 0 0 16px;
                                            vertical-align: top;
                                        "
                                    >
                                        <a
                                            href="mailto:{{ $enquiry['email'] }}"
                                            style="color: #8a612b"
                                        >
                                            {{ $enquiry['email'] }}
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding: 0 16px 16px 0;
                                            color: #756d62;
                                            font-size: 12px;
                                            font-weight: 700;
                                            text-transform: uppercase;
                                            vertical-align: top;
                                        "
                                    >
                                        Phone
                                    </td>

                                    <td
                                        style="
                                            padding: 0 0 16px;
                                            color: #09271f;
                                            vertical-align: top;
                                        "
                                    >
                                        {{ $enquiry['phone'] ?: 'Not supplied' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding: 0 16px 16px 0;
                                            color: #756d62;
                                            font-size: 12px;
                                            font-weight: 700;
                                            text-transform: uppercase;
                                            vertical-align: top;
                                        "
                                    >
                                        Subject
                                    </td>

                                    <td
                                        style="
                                            padding: 0 0 16px;
                                            color: #09271f;
                                            font-weight: 700;
                                            vertical-align: top;
                                        "
                                    >
                                        {{ $enquiry['subject'] }}
                                    </td>
                                </tr>
                            </table>

                            <div
                                style="
                                    margin-top: 8px;
                                    padding: 22px;
                                    border-radius: 14px;
                                    background: #f5efe3;
                                    color: #29231c;
                                    line-height: 1.7;
                                    white-space: pre-line;
                                "
                            >{{ $enquiry['message'] }}</div>

                            <p
                                style="
                                    margin: 24px 0 0;
                                    color: #756d62;
                                    font-size: 13px;
                                    line-height: 1.6;
                                "
                            >
                                Replying to this email will send your response
                                directly to the guest.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
