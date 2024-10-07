const districtMapping = {
    "BD-05": "Bagerhat",
    "BD-01": "Bandarban",
    "BD-02": "Barguna",
    "BD-06": "Barisal",
    "BD-07": "Bhola",
    "BD-03": "Bogra",
    "BD-04": "Brahmanbaria",
    "BD-09": "Chandpur",
    "BD-10": "Chittagong",
    "BD-12": "Chuadanga",
    "BD-11": "Cox",
    "BD-08": "Comilla",
    "BD-13": "Dhaka",
    "BD-14": "Dinajpur",
    "BD-15": "Faridpur",
    "BD-16": "Feni",
    "BD-19": "Gaibandha",
    "BD-18": "Gazipur",
    "BD-17": "Gopalganj",
    "BD-20": "Habiganj",
    "BD-21": "Jamalpur",
    "BD-22": "Jessore",
    "BD-25": "Jhalokathi",
    "BD-23": "Jhenaidah",
    "BD-24": "Joypurhat",
    "BD-29": "Khagrachari",
    "BD-27": "Khulna",
    "BD-26": "Kishoreganj",
    "BD-28": "Kurigram",
    "BD-30": "Kushtia",
    "BD-31": "Laksmipur",
    "BD-32": "Lalmonirhat",
    "BD-36": "Madaripur",
    "BD-37": "Magura",
    "BD-33": "Manikganj",
    "BD-39": "Meherpur",
    "BD-38": "Moulvibazar",
    "BD-35": "Munshiganj",
    "BD-34": "Mymensingh",
    "BD-48": "Naogaon",
    "BD-43": "Narail",
    "BD-40": "Narayanganj",
    "BD-42": "Norshingdi",
    "BD-44": "Natore",
    "BD-45": "Nawabganj",
    "BD-41": "Netrokona",
    "BD-46": "Nilphamari",
    "BD-47": "Noakhali",
    "BD-49": "Pabna",
    "BD-52": "Panchagarh",
    "BD-51": "Patuakhali",
    "BD-50": "Perojpur",
    "BD-53": "Rajbari",
    "BD-54": "Rajshahi",
    "BD-56": "Rangamati",
    "BD-55": "Rangpur",
    "BD-58": "Satkhira",
    "BD-62": "Shariatpur",
    "BD-57": "Sherpur",
    "BD-59": "Sirajganj",
    "BD-61": "Sunamganj",
    "BD-60": "Sylhet",
    "BD-63": "Tangail",
    "BD-64": "Thakurgaon"
};



jQuery(document).ready(function($) {
    var deliveryAreaId = '';
    var deliveryAreaName = '';

    // Handles state change and populates the delivery area dropdown
    $('#billing_state').change(function() {
        var districtCode = $(this).val(); // Captures the district code
        var districtName = districtMapping[districtCode]; // Translates code to name using the mapping

        $.ajax({
            url: redx_params.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_redx_zones', // Matches the action in add_action hooks
                security: redx_params.nonce, // Uses the nonce for security
                district_name: districtName // Sends the actual district name
            },
            success: function(response) {
                if (response.success && response.data.areas) {
                    var $deliveryAreaDropdown = $('#billing_delivery_area');
                    $deliveryAreaDropdown.empty(); // Clear existing options before adding new ones
                    
                    // Append a default or placeholder option
                    $deliveryAreaDropdown.append($('<option>', {
                        value: '',
                        text: 'Select your delivery area' // Placeholder option
                    }));

                    // Populate dropdown with areas
                    $.each(response.data.areas, function(index, area) {
                        $deliveryAreaDropdown.append($('<option>', {
                            value: area.id + ' ' + area.name, // Combining ID and Name for the option value
                            text: area.name // Using just the name for the dropdown display
                        }));
                    });
                } else {
                    // Handle case where 'areas' data is not found
                    $('#billing_delivery_area').empty().append($('<option>', {
                        value: '',
                        text: 'No areas found'
                    }));
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching delivery zones: " + error);
                alert('Error fetching delivery zones.');
            }
        });
    });

    // Capture the selected delivery area ID and Name
    $('#billing_delivery_area').change(function() {
        var selected = $(this).find('option:selected').val().split(' ', 2);
        deliveryAreaId = selected[0];
        deliveryAreaName = selected.slice(1).join(' ');
    });

    // Example submission event, replace '#your_order_submit_button' with your actual submit trigger
    $('#your_order_submit_button').on('click', function(e) {
        e.preventDefault(); // Prevent the default action

        // Proceed to include deliveryAreaId and deliveryAreaName in your order AJAX submission
        $.ajax({
            url: redx_params.ajax_url,
            type: 'POST',
            data: {
                action: 'send_redx_order', // Ensure this action is handled in your PHP
                security: redx_params.nonce,
                delivery_area_id: deliveryAreaId,
                delivery_area: deliveryAreaName,
                // Include other necessary order data
            },
            success: function(response) {
                alert('Order submitted successfully.');
                // Additional success handling
            },
            error: function(xhr, status, error) {
                console.error('Error submitting order: ' + error);
                alert('Error submitting order.');
            }
        });
    });
});






