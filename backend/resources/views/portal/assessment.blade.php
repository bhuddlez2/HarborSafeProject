@extends('portal.layout')

@section('content')
    <h1>Lethality Assessment</h1>

    <form method="POST" action="/portal/assessment">
        @csrf

        <div class="question">
            <p>1. Have they used any weapons?</p>
            <label><input type="radio" name="RiskIndicator1" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator1" value="0"> No</label>
        </div>

        <div class="question">
            <p>2. Have they strangled you?</p>
            <label><input type="radio" name="RiskIndicator2" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator2" value="0"> No</label>
        </div>

        <div class="question">
            <p>3. Do they own any firearms?</p>
            <label><input type="radio" name="RiskIndicator3" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator3" value="0"> No</label>
        </div>

        <div class="question">
            <p>4. Are there children present?</p>
            <label><input type="radio" name="RiskIndicator4" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator4" value="0"> No</label>
        </div>

        <div class="question">
            <p>5. Have they threated to kill you and/or your kids?</p>
            <label><input type="radio" name="RiskIndicator5" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator5" value="0"> No</label>
        </div>

        <div class="question">
            <p>6. Are they a registered sex offender?</p>
            <label><input type="radio" name="RiskIndicator6" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator6" value="0"> No</label>
        </div>

        <div class="question">
            <p>7. Are they affiliated with a gang?</p>
            <label><input type="radio" name="RiskIndicator7" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator7" value="0"> No</label>
        </div>

        <div class="question">
            <p>8. Are they in a gang?</p>
            <label><input type="radio" name="RiskIndicator8" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator8" value="0"> No</label>
        </div>

        <div class="question">
            <p>9. Are they a serial abuser?</p>
            <label><input type="radio" name="RiskIndicator9" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator9" value="0"> No</label>
        </div>

        <div class="question">
            <p>10. Do they have an active Order of Protection?</p>
            <label><input type="radio" name="RiskIndicator10" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator10" value="0"> No</label>
        </div>

        <div class="question">
            <p>11. Is human trafficking involved?</p>
            <label><input type="radio" name="RiskIndicator11" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator11" value="0"> No</label>
        </div>

        <button type="submit">Submit Assessment</button>
    </form>
@endsection