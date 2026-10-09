<div style="font-family: 'Nunito', sans-serif; max-width: 680px; margin: 0 auto;">

    {{-- Card --}}
    <div style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; background: #fff;">

        {{-- Header row --}}
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <div style="width: 44px; height: 44px; background: #ede3f5; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#5C0F8B" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <div>
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #5C0F8B; margin-bottom: 2px;">Newsletter</div>
                @if($issueDate)
                <div style="font-size: 13px; color: #6b7280;">{{ $issueDate->format('F Y') }}</div>
                @endif
            </div>
            @if(!$isPublished)
            <span style="margin-left: auto; background: #f3f4f6; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 999px;">Draft</span>
            @endif
        </div>

        {{-- Title --}}
        <h2 style="font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 12px;">
            {{ filled($title) ? $title : '(No title yet)' }}
        </h2>

        {{-- Summary --}}
        @if(filled($summary))
        <p style="font-size: 15px; color: #374151; margin: 0 0 20px; line-height: 1.6;">{{ $summary }}</p>
        @endif

        {{-- File info --}}
        <div style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#5C0F8B" stroke-width="1.8" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
            </svg>
            @if($hasFile)
            <span style="font-size: 14px; color: #374151; font-weight: 500;">
                PDF attached
                @if($fileSizeBytes)
                &middot; {{ $fileSizeBytes >= 1048576 ? round($fileSizeBytes / 1048576, 1) . ' MB' : round($fileSizeBytes / 1024) . ' KB' }}
                @endif
                @if($filePages)
                &middot; {{ $filePages }} {{ $filePages == 1 ? 'page' : 'pages' }}
                @endif
            </span>
            @else
            <span style="font-size: 14px; color: #9ca3af;">No PDF attached yet</span>
            @endif
        </div>

        @if(!filled($title) && !$issueDate)
        <p style="font-size: 13px; color: #9ca3af; margin-top: 16px;">Fill in the earlier steps to see the preview update.</p>
        @endif
    </div>

    {{-- Status notice --}}
    @if(!$isPublished)
    <div style="margin-top: 12px; padding: 10px 16px; background: #fef9c3; border-radius: 8px; font-size: 13px; color: #854d0e; display: flex; gap: 8px; align-items: center;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        This newsletter is set to <strong>Draft</strong> — it won't appear on the website until Published is turned on.
    </div>
    @endif

</div>
