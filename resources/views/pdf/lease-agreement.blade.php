<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $lease->lease_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            padding: 40px 50px;
            line-height: 1.55;
        }

        .cover {
            text-align: center;
            padding-top: 160px;
        }
        .cover-title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }
        .cover-between { font-size: 12px; color: #555; margin-bottom: 8px; }
        .cover-party { font-size: 15px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        .cover-party-line { font-size: 11px; color: #444; margin-bottom: 20px; }
        .cover-respect { font-size: 11px; color: #555; margin-top: 30px; }
        .cover-suite { font-size: 14px; font-weight: 700; margin-top: 6px; }
        .cover-location { font-size: 11px; color: #444; margin-top: 4px; }
        .cover-date { font-size: 11px; color: #555; margin-top: 40px; }

        .page-break { page-break-before: always; }

        h1.section {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin: 18px 0 8px;
        }
        h2.part {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 22px 0 8px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }
        p { margin-bottom: 8px; text-align: justify; }
        .clause-title { font-weight: 700; }
        ol.clause-list { margin: 0 0 8px 30px; list-style-type: lower-roman; }
        ol.clause-list li { margin-bottom: 8px; text-align: justify; padding-left: 4px; }
        ol.clause-list.decimal { list-style-type: decimal; }
        ol.clause-list.decimal li > ol { margin-top: 8px; }
        ol.clause-list-alpha { margin: 8px 0 0 26px; list-style-type: lower-alpha; }
        ol.clause-list-alpha li { margin-bottom: 8px; text-align: justify; padding-left: 4px; }

        table.def-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.def-table td {
            padding: 6px 8px;
            font-size: 10.5px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        table.def-table td.def-num { width: 34px; font-weight: 700; }
        table.def-table td.def-label { width: 130px; font-weight: 700; }

        .sig-block { margin-top: 26px; }
        .sig-line { margin-bottom: 6px; }
        .sig-dots { border-bottom: 1px solid #333; display: inline-block; width: 320px; }

        .footer-page {
            margin-top: 20px;
            font-size: 9px;
            color: #888;
            text-align: right;
        }
    </style>
</head>
<body>

{{-- ── Cover Page ── --}}
<div class="cover">
    <div class="cover-title">Lease Agreement</div>
    <div class="cover-between">Between</div>
    <div class="cover-party">{{ $lessorName }}</div>
    <div class="cover-party-line">{{ $lessorAddress }}<br>(&ldquo;Lessor&rdquo;)</div>
    <div class="cover-between">And</div>
    <div class="cover-party">{{ $lesseeName }}</div>
    <div class="cover-party-line">{{ $lesseeAddress }}<br>(&ldquo;Lessee&rdquo;)</div>
    <div class="cover-respect">In Respect of:</div>
    <div class="cover-suite">{{ $unit->unit_number }}{{ $floorLabel ? ' — ' . $floorLabel : '' }}</div>
    <div class="cover-location">{{ $property->name }}{{ $property->address ? ', ' . $property->address : '' }}{{ $property->city ? ', ' . $property->city : '' }}</div>
    <div class="cover-date">Dated this {{ $agreementDate->format('jS \d\a\y \o\f F, Y') }}</div>
</div>

<div class="page-break"></div>

{{-- ── Date and Parties ── --}}
<h1 class="section">Date and Parties</h1>
<p>THIS LEASE is made on {{ $agreementDate->format('d F, Y') }} between</p>
<p>{{ $lessorName }} of {{ $lessorAddress }}{{ $lessorRegNo ? ', a limited liability company registered under certificate number ' . $lessorRegNo : '' }}, hereafter referred to as &ldquo;the Lessor&rdquo;</p>
<p>And</p>
@if($tenant->tenant_type === 'company')
<p>{{ $lesseeName }} of {{ $lesseeAddress }}{{ $tenant->registration_number ? ', a limited liability company registered under certificate number ' . $tenant->registration_number : '' }}, hereafter referred to as &ldquo;the Lessee&rdquo;.</p>
@else
<p>{{ $lesseeName }} of {{ $lesseeAddress }}{{ $tenant->national_id ? ', holder of National ID number ' . $tenant->national_id : '' }}, hereafter referred to as &ldquo;the Lessee&rdquo;.</p>
@endif

<h2 class="part">Part A: Introduction</h2>
<h1 class="section">Definitions</h1>
<p>In this Lease the following terms shall have the following meanings:</p>
<table class="def-table">
    <tr>
        <td class="def-num">1.1</td>
        <td class="def-label">Commencement Date:</td>
        <td>{{ $commencementDate->format('d F Y') }}</td>
    </tr>
    <tr>
        <td class="def-num">1.2</td>
        <td class="def-label">Permitted Use:</td>
        <td>{{ $unit->type }} Premises</td>
    </tr>
    <tr>
        <td class="def-num">1.3</td>
        <td class="def-label">Plan:</td>
        <td>Plan of the Premises attached to this lease.</td>
    </tr>
    <tr>
        <td class="def-num">1.4</td>
        <td class="def-label">Property:</td>
        <td>
            {{ $unit->unit_number }}{{ $floorLabel ? ' located on ' . $floorLabel : '' }} of {{ $property->name }} situated at {{ $property->address ?? 'the property address' }}, containing {{ number_format((float)$unit->size_sqm, 2) }} square meters and rent will be charged at {{ $currency }} {{ number_format((float)($unit->rate_per_sqm + $unit->service_charge_per_sqm), 2) }} per square meter per month inclusive of service charge (being rent {{ $currency }} {{ number_format((float)$unit->rate_per_sqm, 2) }} and service charge {{ $currency }} {{ number_format((float)$unit->service_charge_per_sqm, 2) }} per sq.mt) before VAT.
        </td>
    </tr>
    <tr>
        <td class="def-num">1.5</td>
        <td class="def-label">Lease Term:</td>
        <td>The lease term shall commence on {{ $commencementDate->format('d F Y') }} and continue for a minimum period of {{ $leaseTermLabel }} (&ldquo;Initial Term&rdquo;).</td>
    </tr>
    <tr>
        <td class="def-num">1.6</td>
        <td class="def-label">Rent:</td>
        <td>Monthly rent of {{ $currency }} {{ number_format((float)$lease->monthly_rent, 2) }} VAT exclusive during the Initial Term, thereafter subject to review.</td>
    </tr>
    <tr>
        <td class="def-num">1.7</td>
        <td class="def-label">Service Charge:</td>
        <td>Monthly service charge of {{ $currency }} {{ number_format((float)$unit->service_charge, 2) }} VAT exclusive.</td>
    </tr>
    <tr>
        <td class="def-num">1.8</td>
        <td class="def-label">Payment Date:</td>
        <td>The rent shall be paid in {{ $installmentsPerYear }} instalment(s) per year, that is every {{ $installmentIntervalMonths }} month(s).</td>
    </tr>
    <tr>
        <td class="def-num">1.9</td>
        <td class="def-label">Review Dates:</td>
        <td>Means at the expiry of the first one year of the lease term.</td>
    </tr>
    <tr>
        <td class="def-num">1.10</td>
        <td class="def-label">Rights:</td>
        <td>Means the rights described in this lease.</td>
    </tr>
    <tr>
        <td class="def-num">1.11</td>
        <td class="def-label">Service Channels:</td>
        <td>Means all facilities for the supply of drainage, water, electricity, telecommunication and other services including cisterns, sewers, pipes, drains, wires, cables, ducts and aerials.</td>
    </tr>
    <tr>
        <td class="def-num">1.12</td>
        <td class="def-label">Clause and Schedule:</td>
        <td>Means respectively clauses or schedules in this lease unless the content shows a contrary meaning.</td>
    </tr>
</table>

<h2 class="part">Part B: Grant to Lessee</h2>
<p class="clause-title">Letting</p>
<p>The Lessor lets and the Lessee take the property and the rights at the rent and on the terms set out in this lease.</p>
<p class="clause-title">Commencement and Notice Termination</p>
<p>This lease starts at the Commencement Date and shall remain in force for the term of {{ $leaseTermLabel }} and then until it is terminated by not less than 3 months prior written notice by any party to the other.</p>
<p class="clause-title">Quiet Enjoyment</p>
<p>The Lessor agrees that if the lessee pays the rent and any other money payable under this lease and complies with all its obligations under it including but not limited to mutual respect of other lessees and users of the building, it may quietly hold and enjoy the property during the term without any interruption or disturbance by the lessor or any person claiming under or in trust for him.</p>
<p>Noise levels including and not limited to loud music, broadcasting are prohibited as they affect the quiet enjoyment of other tenants.</p>

<h2 class="part">Part C: Lessor Rights</h2>
<p class="clause-title">5. Lessor Access to Property</p>
<p>The Lessee shall give the Lessor, or anyone authorised by him in writing, access to the property for the purposes of inspecting the condition of the property, during work which the Lessor is required or permitted to do under this lease, complying with any statutory obligations, viewing the property as a prospective buyer or mortgagee, or during the last 6 months of the lease period, as a prospective tenant, valuing the property, or inspecting, cleaning, decorating, maintaining, or repairing neighbouring property or any service channels servicing neighbouring property.</p>
<p>Except in emergencies, access under this clause may be exercised only on seven days written notice and during normal business hours.</p>
<p class="clause-title">6. Tenant Not to Divert Service</p>
<p>The Lessee shall ensure that none of the service channels serving all or any of the property are obstructed, terminated, diverted or interfered with in any way.</p>

<h2 class="part">Part D: Rent and Other Financial Provisions</h2>
<p class="clause-title">7. Rent and Other Financial Provisions</p>
<p>The Lessee shall pay rent and VAT (Value Added Tax) as per the TRA rate to the Lessor. The lessor shall give an acknowledgment of receipt of rent in writing and that the receipt shall be conclusive evidence of payment of such rent.</p>
<p>The rent payment schedule for the term of this lease shall be every {{ $installmentIntervalMonths }} month(s). The total rent payment per instalment is {{ $currency }} {{ number_format($rentPerInstallment, 2) }} + VAT.</p>
<p>In the event the Lessee defaults on the timely arranged payment schedule as stipulated, herein after a 7 days reminder notice, the Lessor may lock the office with a notice of 30 days for non-payment.</p>
<p>Lessee will deduct Withholding Tax from rent at the applicable TRA rate of {{ rtrim(rtrim(number_format((float)$lease->wht_rate, 2), '0'), '.') }}% and provide the Lessor with the respective Withholding Tax Certificates from TRA within 14 days.</p>
<p>The Lessee will pay a deposit equivalent to one (1) month rent and service charge amounting to {{ $currency }} {{ number_format((float)$lease->deposit, 2) }} before entering the property. The money will be refunded upon the end of the lease with deductions made for any damages to fixtures and fittings for repairs that may need to be done.</p>

<p class="clause-title">8. Utilities</p>
<p>In addition to the rent, a service charge will be levied to cover all outgoings, operational costs and overheads relating to the building.</p>
<p>The service charge is based on {{ $currency }} {{ number_format((float)$unit->service_charge_per_sqm, 2) }} per square meter per month, equating to {{ $currency }} {{ number_format((float)$unit->service_charge, 2) }} per month exclusive of VAT.</p>
<p>The Lessee will be responsible for payment of electricity, telecommunication services, internet services and generator usage.</p>
<p>The backup generator payment shall be according to the unit consumption from the energy meter.</p>

<p class="clause-title">Facilities</p>
<p>Provision has been made for Air Conditioning (AC) outlets. The purchase and installation of the AC outlets will be covered by the Lessee. At the end of the tenancy the Lessee can vacate with all their respective complete AC unit sets.</p>
<p>All floors have access points to electricity. Within reason and with consent from the Lessor the Lessee shall be allowed to add suitable outlet points for their office/commercial space. The cost of any additions shall be covered by the Lessee.</p>

<p class="clause-title">Signage</p>
<p>Following the Lessor's consent, which shall not be unreasonably withheld, the Lessee shall have the right to place on the Premises, at locations selected by the Lessee which are permitted by applicable zoning ordinances and private restrictions, any signs displaying the name and logo of the Lessee.</p>
<p>The Lessor may refuse consent to any proposed signage that is, in the Lessor's reasonable opinion, too large, deceptive, unattractive or otherwise inconsistent with or inappropriate to the Premises or use of any other Lessee.</p>

<p class="clause-title">Lessor to Pay Taxes and Outgoings</p>
<p>The Lessor shall pay property taxes in respect of the property as per the government regulations.</p>

<p class="clause-title">Security</p>
<p>The Lessor will provide day and night security services to the Property. The Lessee shall be responsible for its security costs within the Premises and the Lessor will not be liable for any loss of the contents of the same.</p>

<p class="clause-title">Legal Documents</p>
<p>The Lessee shall provide copies of the following documents for the Lessor's records:</p>
<ol class="clause-list">
    <li>Certificate of Incorporation</li>
    <li>BRELA Registration Extract</li>
    <li>TIN &amp; VAT Certificate</li>
    <li>Valid Business License</li>
    <li>For lease signatory: identification documents (passport/NIDA)</li>
</ol>

<p class="clause-title">Value Added Tax (VAT)</p>
<p>The Lessor is VAT registered so under the law the Lessor will charge VAT to the Lessee on the rent at the current applicable rate of {{ rtrim(rtrim(number_format((float)$lease->vat_rate, 2), '0'), '.') }}% and issue an official receipt for it as per the law.</p>

<p class="clause-title">Abatement of Rent</p>
<p>When any part of the property is significantly destroyed or damaged, and the destruction or damage is not caused by the Lessee, the period of suspension shall be from the date of the destruction or damage and shall continue for a maximum of three months. Within the three months period, to the best that is feasibly possible, the property will be rebuilt and restored.</p>
<p>If the property is not wholly destroyed or damaged and remains in part reasonably inhabitable or fit for use, a fair proportion and not the whole of the rent shall be suspended according to the nature and extent of destruction or damage.</p>
<p>If there is any dispute between the Lessor and the Lessee about the amount or period of suspension of the rent, both parties will try to reach an amicable settlement in the good spirit of the continuation of the tenancy.</p>

<div class="page-break"></div>

<h2 class="part">Part E: Structure and Maintenance</h2>
<p class="clause-title">16. Not Add to or Alter Property</p>
<p>The Lessee shall not, without first obtaining the Lessor's written consent, erect any new building on the property, make any internal or external alterations or additions to the property or to any building on it, or cut or damage any of the load bearing walls or other structural parts of any buildings on the property.</p>
<p class="clause-title">17. Restore Original State of Property</p>
<p>If the Lessee makes any addition or alteration to the property, the Lessee shall at the end or earlier termination of the term and at the Lessee's own cost reinstate the property to the Lessor's entire satisfaction and restore it as if such addition or alteration had not been made, and pay the expenses incurred by the Lessor including legal charges and surveyors' fees in connection with superintending the reinstatement.</p>
<p class="clause-title">18. Notify of Damage</p>
<p>The Lessee shall give notice to the Lessor immediately after its occurrence of any damage to or destruction of the property or any part of it, describing its extent and stating, if possible, its cause.</p>

<h2 class="part">Part F: Use and Occupation</h2>
<p class="clause-title">19. Use of Space</p>
<p>The Lessee shall not use the property except for the permitted use, and shall not use the property for any offensive, noisy, dangerous, illegal, immoral or improper activities.</p>
<p class="clause-title">20. No Underletting or Assignment</p>
<p>The Lessee shall not assign, underlet or otherwise share or part with possession of any part of the property without first obtaining the Lessor's consent.</p>

<h2 class="part">Part G: Termination</h2>
<p class="clause-title">21. Yield Up</p>
<p>At the end of the term the Lessee shall abide by the following: -</p>
<ol class="clause-list">
    <li>To surrender to the Lessor all keys giving access to all parts of the Premises, irrespective of whether or not the same have been supplied by the Lessor;</li>
    <li>Quietly to yield up the Premises and the Lessor's fittings and fixtures to the reasonable satisfaction of the Lessor. This shall be done through the removal of all the tenant's fixtures &amp; fittings, ensuring that the premises are repaired and cleaned in accordance with the Lessee's covenants contained in this Agreement.</li>
    <li>To redecorate the premises to the reasonable satisfaction of the Lessor, with two coats of good quality paint. In the case of any property destruction not from normal wear and tear, the property should be repaired/reinstated in a good workmanlike manner using suitable and appropriate materials as the Lessor may reasonably and properly see fit.</li>
    <li>At any time upon the written request of the Lessor, to remove any signs, names, advertisements or notices erected upon or affixed to, within or outside the Premises or the exterior walls of the building and to make good any damage or disfigurement caused by reason of such erection, affixing and/or removal thereof.</li>
</ol>
<p>For the avoidance of doubt, the deposit will not be payable until the final handover of the demised premises, taking into account the above-mentioned clauses.</p>
<p class="clause-title">22. Termination in Unfit for Use</p>
<p>This lease comes to an end if either party gives the other party at least three (03) months written notice to terminate it, where the property is destroyed or damaged by any cause other than any act, default, or omission of the Lessee, rendering it unfit for occupation, and it is not possible or practical to reinstate it within six months of the destruction or damage.</p>

<h2 class="part">Part H: Administrative and Miscellaneous</h2>
<p class="clause-title">23. Not Cause Penalty on Lessor</p>
<p>The Lessee shall not at any time do anything on the Property in respect of which the Lessor incurs or becomes liable to pay any penalty, damages, compensation or expenses.</p>
<p class="clause-title">24. Communication and Notices</p>
<p>Any notices, requests or other communications required or permitted under this Agreement must be in writing, and may be delivered by hand or sent by airmail, e-mail, or facsimile to the party's address specified below, or such other address as notified from time to time.</p>

<table class="def-table">
    <tr>
        <td class="def-label">For the Lessor:</td>
        <td>
            {{ $lessorName }}<br>
            {{ $lessorAddress }}<br>
            @if($companyPhone) Tel: {{ $companyPhone }}<br> @endif
            @if($companyEmail) E-mail: {{ $companyEmail }}<br> @endif
        </td>
    </tr>
    <tr>
        <td class="def-label">For the Lessee:</td>
        <td>
            {{ $lesseeName }}{{ $tenant->contact_person ? ' — Attn: ' . $tenant->contact_person : '' }}<br>
            {{ $lesseeAddress }}<br>
            @if($tenant->phone) Mobile: {{ $tenant->phone }}<br> @endif
            @if($tenant->email) E-mail: {{ $tenant->email }}<br> @endif
        </td>
    </tr>
</table>

<p class="clause-title">25. Force Majeure</p>
<p>Each party shall be excused from performance of its obligations by any event of force majeure occurring, for so long as the condition constituting such force majeure continues, plus thirty days. Force majeure events include causes beyond the control of the lessor or lessee, including acts of God, regulations or law of any government, war, civil commotion, destruction of production facilities or materials by fire, earthquake or storm, labour disturbances, epidemic and failure of public utilities.</p>
<p class="clause-title">26. Succession and Assigns</p>
<p>Except as otherwise provided herein, the rights and obligations created hereunder shall inure to the benefit of and be binding upon the heirs, successors and authorised assigns of the parties hereto.</p>
<p class="clause-title">27. Disputes Resolution</p>
<p>Both the Lessor and the Lessee will make every effort to resolve amicably by informal negotiations any disagreement or dispute arising between them under or in connection with this Agreement. If after 30 days from the commencement of such informal negotiations the parties are unable to resolve any such dispute, either party may require that the dispute be referred to Arbitration for resolution.</p>
<p class="clause-title">28. Arbitration</p>
<ol class="clause-list decimal">
    <li>Any dispute and controversy arising out of or otherwise relating to this agreement shall be finally and exclusively settled by a panel of three (3) arbitrators.</li>
    <li>The moving party shall serve upon the other party the complaint and simultaneously appoint one (1) arbitrator. The other party shall thereupon appoint a second arbitrator by notice duly given to the first party within thirty (30) days of the date of receipt by it of the notice of the moving party. The two arbitrators so appointed shall agree upon the appointment of the third arbitrator, who shall also act as the chairman of the panel, within twenty-one (21) days of the appointment of the second of them. If a party fails to appoint the second arbitrator within the prescribed time, or if the two arbitrators fail to agree upon the appointment of the chairman within the prescribed time, the missing arbitrator or chairman shall be appointed in accordance with the Arbitration Ordinance (Cap. No. 15).</li>
    <li>All arbitrators shall be proficient and competent in the English language.</li>
    <li>Any award rendered by the arbitrators shall be final and binding upon both parties hereto, shall be abided to in good faith, and may be registered in any court of law having jurisdiction therefor.</li>
</ol>
<p class="clause-title">29. Entire Agreement</p>
<p>This agreement constitutes the entire agreement between the parties pertaining to the subject matter hereof. Any and all prior written or oral agreements between the parties pertaining to the subject matter hereof are expressly cancelled. Any modification of this agreement shall be in writing and signed by the authorised representatives of both parties.</p>
<p class="clause-title">30. Governing Law</p>
<p>This agreement shall be governed by and construed in accordance with the laws of the United Republic of Tanzania.</p>
<p class="clause-title">31. Costs</p>
<ol class="clause-list decimal">
    <li>Lessee is responsible for all charges, cost and expenses related to this agreement including stamp duty.</li>
    <li>
        Advocate fees, where applicable shall be borne by respective parties which have been incurred in connection with:
        <ol class="clause-list-alpha">
            <li>The preparation, execution and delivery of this agreement.</li>
            <li>Any actual or proposed amendments, variation, supplement.</li>
            <li>Waiver or consent under or in connection with this agreement.</li>
            <li>Any discharge of release of this agreement; and</li>
            <li>The preservation or exercise (or attempted preservation or exercise), and the enforcement (or attempted enforcement) of any rights under, or in connection with the agreement.</li>
        </ol>
    </li>
</ol>

<div class="sig-block">
    <p>IN WITNESS WHEREOF the parties hereto have duly executed this agreement as per the terms in the manner as herein prescribed.</p>

    <p style="margin-top:20px;">SIGNED by the said LESSOR</p>
    <p class="clause-title">{{ $lessorName }}</p>
    <p>{{ $property->city ?? 'Dar-es-Salaam' }} and in our presence</p>
    <p class="sig-line">This ……… day of …………………………….</p>
    <p class="sig-line">Name: <span class="sig-dots"></span></p>
    <p class="sig-line">Signature: <span class="sig-dots"></span></p>
    <p class="sig-line">Designation: <span class="sig-dots"></span></p>
    <p class="clause-title">Witness by:</p>
    <p class="sig-line">Name: <span class="sig-dots"></span></p>
    <p class="sig-line">Signature: <span class="sig-dots"></span></p>
    <p class="sig-line">Designation: <span class="sig-dots"></span></p>

    <p style="margin-top:20px;">SIGNED{{ $tenant->tenant_type === 'company' ? ', SEALED with the common seal of the LESSEE' : ' by the said LESSEE' }}</p>
    <p class="clause-title">{{ $lesseeName }}</p>
    <p>In {{ $property->city ?? 'Dar-es-Salaam' }} and in our presence</p>
    <p class="sig-line">This ……… day of …………………………….</p>
    <p class="sig-line">Name: <span class="sig-dots"></span></p>
    <p class="sig-line">Signature: <span class="sig-dots"></span></p>
    <p class="sig-line">Designation: <span class="sig-dots"></span></p>
    <p class="clause-title">Witness by:</p>
    <p class="sig-line">Name: <span class="sig-dots"></span></p>
    <p class="sig-line">Signature: <span class="sig-dots"></span></p>
    <p class="sig-line">Designation: <span class="sig-dots"></span></p>
</div>

<div class="footer-page">{{ $lease->lease_number }} · Generated {{ \Carbon\Carbon::now()->format('d/m/Y') }}</div>

</body>
</html>
