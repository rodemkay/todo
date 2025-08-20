jQuery(document).ready(function($) {
    // Save plan HTML via AJAX
    window.savePlanHTML = function(todoId, planHTML) {
        $.post(ajaxurl, {
            action: 'save_plan_html',
            nonce: wpProjectTodos.nonce,
            todo_id: todoId,
            plan_html: planHTML
        }, function(response) {
            if (response.success) {
                console.log('Plan saved successfully');
                // Reload the page to show the plan viewer
                location.reload();
            } else {
                alert('Fehler beim Speichern des Plans: ' + response.data.message);
            }
        });
    };
    
    // Get plan HTML via AJAX
    window.getPlanHTML = function(todoId) {
        $.post(ajaxurl, {
            action: 'get_plan_html',
            nonce: wpProjectTodos.nonce,
            todo_id: todoId
        }, function(response) {
            if (response.success) {
                return response.data.html;
            }
        });
    };
});