<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $workspaceName }}</title>
</head>
<body style="margin:0; padding:0; background:#F4F6F8; font-family: -apple-system, 'Segoe UI', Tahoma, Arial, sans-serif; color:#0E1726;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6F8; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#FFFFFF; border:1px solid #E4E9F0; border-radius:14px; overflow:hidden;">
                    {{-- Branded header: the WORKSPACE's logo + name --}}
                    <tr>
                        <td align="center" style="padding:28px 24px 14px;">
                            @if (! empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="{{ $workspaceName }}" style="max-height:56px; max-width:200px; display:block; margin:0 auto 8px;">
                            @endif
                            <div style="font-size:20px; font-weight:bold; color:#1F82C7;">{{ $workspaceName }}</div>
                        </td>
                    </tr>
                    <tr><td style="padding:0 24px;"><div style="height:1px; background:#E4E9F0;"></div></td></tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:22px 28px;">
                            @foreach ($lines as $line)
                                <p style="margin:0 0 12px; font-size:14px; line-height:1.9;">{{ $line }}</p>
                            @endforeach
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:14px 24px 26px;" align="center">
                            <div style="font-size:11px; color:#98A2B3;">{{ $workspaceName }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
