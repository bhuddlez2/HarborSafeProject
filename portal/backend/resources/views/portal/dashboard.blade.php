@extends('portal.layout')

@section('content')
    <h1>Basic Information</h1>

    @if(session('success'))
        <div class="success-message">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="error-message">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bypass-check">
        <label>
            <input type="checkbox" id="anonymousToggle">
            Would you like to stay anonymous?
        </label>
    </div>

    {{-- Full form - hidden when checkbox is checked --}}
    <div id="fullForm">
        <form method="POST" action="/portal/dashboard" class="info-form">
            @csrf
            <input type="hidden" name="submission_type" value="full">

            <div class="form-row">
                <div class="form-group">
                    <label for="FirstName">First Name</label>
                    <input type="text" id="FirstName" name="FirstName"
                        value="{{ old('FirstName') }}" placeholder="First name">
                </div>
                <div class="form-group">
                    <label for="LastName">Last Name</label>
                    <input type="text" id="LastName" name="LastName"
                        value="{{ old('LastName') }}" placeholder="Last name">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="DateOfBirth">Date of Birth</label>
                    <input type="date" id="DateOfBirth" name="DateOfBirth"
                        value="{{ old('DateOfBirth') }}">
                </div>
                <div class="form-group">
                    <label for="Phone">Safe-to-Contact Phone Number</label>
                    <input type="tel" id="Phone" name="Phone"
                        value="{{ old('Phone') }}" placeholder="(555) 555-5555">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="Address">Street Address</label>
                <input type="text" id="Address" name="Address"
                    value="{{ old('Address') }}" placeholder="123 Main St">
            </div>

            <button type="submit">Save & Continue</button>

        </form>
    </div>

    {{-- Anonymous form - shown when checkbox is checked --}}
    <div id="anonymousForm" style="display: none;">
        <form method="GET" action="/portal/assessment" class="info-form">
            @csrf
            <input type="hidden" name="submission_type" value="anonymous">

            <p class="anon-note">No personal information will be recorded.</p>
            <button type="submit">Continue Anonymously</button>
        </form>
    </div>
@endsection

@section('scripts')
<script>
    // Logic for anonymity functionality
    const toggle = document.getElementById('anonymousToggle');
    const fullForm = document.getElementById('fullForm');
    const anonForm = document.getElementById('anonymousForm');

    toggle.addEventListener('change', function() {
        if (this.checked) {
            fullForm.style.display = 'none';
            anonForm.style.display = 'block';
        } else {
            fullForm.style.display = 'block';
            anonForm.style.display = 'none';
        }
    });
</script>
@endsection