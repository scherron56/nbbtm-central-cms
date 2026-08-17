<!-- CONTACT INFORMATION FIELDSET -->
<fieldset class="form-grid-section-9">
  <legend>
    <h2>Contact Information</h2>
  </legend>
  <div class="field-group" style="--colspan: 9;">
    <label for="address_1">Address</label>
    <input type="text" id="address_1" name="address_1" autocomplete="off" />
  </div>

  <div class="field-group" style="--colspan: 3;">
    <label for="state">State</label>
    <select id="state" name="state">
      <optgroup label="Default Region">
        <option value="MN" selected>Minnesota (MN)</option>
        <option value="WI">Wisconsin (WI)</option>
        <option value="IA">Iowa (IA)</option>
        <option value="ND">North Dakota (ND)</option>
        <option value="SD">South Dakota (SD)</option>
      </optgroup>
      <optgroup label="All States">
        <option value="AL">Alabama (AL)</option><option value="AK">Alaska (AK)</option>
        <option value="AZ">Arizona (AZ)</option><option value="AR">Arkansas (AR)</option>
        <option value="CA">California (CA)</option><option value="CO">Colorado (CO)</option>
        <option value="CT">Connecticut (CT)</option><option value="DE">Delaware (DE)</option>
        <option value="DC">District of Columbia (DC)</option><option value="FL">Florida (FL)</option>
        <option value="GA">Georgia (GA)</option><option value="HI">Hawaii (HI)</option>
        <option value="ID">Idaho (ID)</option><option value="IL">Illinois (IL)</option>
        <option value="IN">Indiana (IN)</option><option value="KS">Kansas (KS)</option>
        <option value="KY">Kentucky (KY)</option><option value="LA">Louisiana (LA)</option>
        <option value="ME">Maine (ME)</option><option value="MD">Maryland (MD)</option>
        <option value="MA">Massachusetts (MA)</option><option value="MI">Michigan (MI)</option>
        <option value="MS">Mississippi (MS)</option><option value="MO">Missouri (MO)</option>
        <option value="MT">Montana (MT)</option><option value="NE">Nebraska (NE)</option>
        <option value="NV">Nevada (NV)</option><option value="NH">New Hampshire (NH)</option>
        <option value="NJ">New Jersey (NJ)</option><option value="NM">New Mexico (NM)</option>
        <option value="NY">New York (NY)</option><option value="NC">North Carolina (NC)</option>
        <option value="OH">Ohio (OH)</option><option value="OK">Oklahoma (OK)</option>
        <option value="OR">Oregon (OR)</option><option value="PA">Pennsylvania (PA)</option>
        <option value="RI">Rhode Island (RI)</option><option value="SC">South Carolina (SC)</option>
        <option value="TN">Tennessee (TN)</option><option value="TX">Texas (TX)</option>
        <option value="UT">Utah (UT)</option><option value="VT">Vermont (VT)</option>
        <option value="VA">Virginia (VA)</option><option value="WA">Washington (WA)</option>
        <option value="WV">West Virginia (WV)</option><option value="WY">Wyoming (WY)</option>
      </optgroup>
    </select>
  </div>

  <div class="field-group" style="--colspan: 3; --rowspan: 1;">
    <label for="city">City</label>
    <select id="city" name="city">
      <option value="">--Select City--</option>
    </select>
  </div>

  <div class="field-group" style="--colspan: 3;">
    <label for="zipcode">Zip Code</label>
    <input type="text" id="zipcode" name="zipcode" maxlength="5" autocomplete="off" placeholder="Enter ZIP" />
  </div>

  <!-- Rest of phone and email fields stay unchanged -->