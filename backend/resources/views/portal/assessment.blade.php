@extends('portal.layout')

@section('content')
    <h1>Lethality Assessment</h1>

    <form method="POST" action="/portal/assessment">
        @csrf

        <div class="question">
            <p>1. Has the violence increased in frequency or severity?</p>
            <label><input type="radio" name="RiskIndicator1" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator1" value="0"> No</label>
        </div>

        <div class="question">
            <p>2. Does the abuser own or have access to a gun?</p>
            <label><input type="radio" name="RiskIndicator2" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator2" value="0"> No</label>
        </div>

        <div class="question">
            <p>3. Has the abuser ever tried to choke or strangle you?</p>
            <label><input type="radio" name="RiskIndicator3" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator3" value="0"> No</label>
        </div>

        <div class="question">
            <p>4. Is the abuser unemployed?</p>
            <label><input type="radio" name="RiskIndicator4" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator4" value="0"> No</label>
        </div>

        <div class="question">
            <p>5. Has the abuser ever tried to kill you?</p>
            <label><input type="radio" name="RiskIndicator5" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator5" value="0"> No</label>
        </div>

        <div class="question">
            <p>6. Does the abuser avoid responsibility by blaming others?</p>
            <label><input type="radio" name="RiskIndicator6" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator6" value="0"> No</label>
        </div>

        <div class="question">
            <p>7. Is the abuser obsessed with you or does he stalk you?</p>
            <label><input type="radio" name="RiskIndicator7" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator7" value="0"> No</label>
        </div>

        <div class="question">
            <p>8. Has the abuser ever threatened to kill you?</p>
            <label><input type="radio" name="RiskIndicator8" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator8" value="0"> No</label>
        </div>

        <div class="question">
            <p>9. Is the abuser violently or constantly jealous?</p>
            <label><input type="radio" name="RiskIndicator9" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator9" value="0"> No</label>
        </div>

        <div class="question">
            <p>10. Have you left or tried to leave this relationship in the past year?</p>
            <label><input type="radio" name="RiskIndicator10" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator10" value="0"> No</label>
        </div>

        <div class="question">
            <p>11. Do you have a child that the abuser knows is not his?</p>
            <label><input type="radio" name="RiskIndicator11" value="1"> Yes</label>
            <label><input type="radio" name="RiskIndicator11" value="0"> No</label>
        </div>

        <button type="submit">Submit Assessment</button>
    </form>
@endsection