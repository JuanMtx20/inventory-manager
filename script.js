// gear icon, when is clicked it should display two options: edit and delete
// we are using bootstrap 5
const actionLayout = `
  <div class="dropdown">
    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-cog"></i>
    </button>
    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
      <li><button class="dropdown-item" onclick="showEditModal(requestId)">Edit</button></li>
      <li><button class="dropdown-item" onclick="showDeleteModal(requestId)">Delete</button></li>
    </ul>
  </div>
`;

let users = [];
let items = [];


function getRequests() {
  console.log('getRequests');
  $('#inventory-table').DataTable().destroy();
  // fetch data from endpoint
  fetch('https://juanmartinez.dev/inventory-manager/api.php?endpoint=requests')
    .then(response => response.json())
    .then(response => {
      const data = response.data;
      // populate table with data from endpoint
      const tableBody = document.querySelector('#inventory-table tbody');
      tableBody.innerHTML = '';
      console.log({ data })
      data.forEach(item => {
        const row = `
        <tr>
          <td>${actionLayout.replaceAll('requestId', item.req_id)}</td>
          <td>${item.requested_by}</td>
          <td>${item.items}</td>
          <td>${item.item_type}</td>
        </tr>
      `;
        tableBody.insertAdjacentHTML('beforeend', row);
      });
    })
    .catch(error => console.error(error))
    .finally(() => {

      $('#inventory-table').DataTable(
        {
          "paging": false,
          "info": false,
          "searching": true,
          "order": [[0, "desc"]]
        }
      );
    });
}

function getUsers() {
  // fetch users
  fetch('https://juanmartinez.dev/inventory-manager/api.php?endpoint=users')
    .then(response => response.json())
    .then(response => {
      users = response.data;
    })
    .catch(error => console.error(error));
}

function getItems() {
  // fetch items
  fetch('https://juanmartinez.dev/inventory-manager/api.php?endpoint=items')
    .then(response => response.json())
    .then(response => {
      items = response.data;
    })
    .catch(error => console.error(error));
}

$(document).ready(function () {
  (function () {
    getRequests();
    getItems();
    getUsers();
  }());

  // fill users and items to select when modal is opened
  $('#requestModal').on('show.bs.modal', function (event) {
    const modal = $(this);

    getUsers();
    // populate select with users from endpoint response
    const selectUsers = modal.find('#user-list');
    selectUsers.html('<option selected value="">Select a user</option>');
    users.forEach(user => {
      const option = `<option value="${user.usr_id}">${user.name}</option>`;
      selectUsers.append(option);
    });

    getItems();
    const selectItems = modal.find('#item-list');
    items.forEach(item => {
      const option = `<option value="${item.id}">${item.item}</option>`;
      selectItems.append(option);
    });
  })

  $("#cancel-request").click(function () {
    $('#requestModal').modal('hide');
    $('.request-items').remove();
    $('#request-form').trigger('reset');
  });

  $("#request-form").submit(function (e) {
    e.preventDefault();
    const form = $(this);
    const formData = form.serializeArray();
    // parse items to send to endpoint
    const items = formData.map((item, index) => item.name === 'items[]' ? item.value : null).filter(item => item !== null);
    const data = {
      user: formData[0].value,
      items,
    };
    console.log({ data, formData });
    // send data to endpoint
    fetch('https://juanmartinez.dev/inventory-manager/api.php?endpoint=requests', {
      method: 'POST',
      // application/x-www-form-urlencoded
      body: new URLSearchParams(data)

    })
      .then(response => response.json())
      .then(response => {
        console.log({ response });
        $('#requestModal').modal('hide');
        $('.request-items').remove();
        $('#request-form').trigger('reset');

        // populate table with data from endpoint
      })
      .catch(error => console.error(error))
      .finally(() => {
        getRequests();
      });
  });

  $("#edit-form").submit(function (e) {
    e.preventDefault();
    const id = $('#edit-item').val();
    const form = $(this);
    const formData = form.serializeArray();
    // parse items to send to endpoint
    const items = formData.map((item, index) => item.name === 'items[]' ? item.value : null).filter(item => item !== null);
    const data = {
      user: formData[0].value,
      items,
    };
    console.log({ data, formData });
    // send data to endpoint
    fetch(`https://juanmartinez.dev/inventory-manager/api.php?endpoint=requests&id=${id}`, {
      method: 'POST',
      // application/x-www-form-urlencoded
      body: new URLSearchParams(data),
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      }
    })
      .then(response => response.json())
      .then(response => {
        console.log({ response });
        $('#editModal').modal('hide');
        $('.request-items').remove();
        $('#edit-form').trigger('reset');

        // populate table with data from endpoint
      })
      .catch(error => console.error(error))
      .finally(() => {
        getRequests();
      });
  });

  $("#delete-request").click(function () {
    const id = $('#delete-item').val();
    fetch(`https://juanmartinez.dev/inventory-manager/api.php?endpoint=requests&id=${id}`, {
      method: 'DELETE',
    })
      .then(response => response.json())
      .then(response => {
        console.log({ response });
        $('#deleteModal').modal('hide');
      })
      .catch(error => console.error(error))
      .finally(() => {
        getRequests();
      });
  }
  );
});

function addItem() {
  // find the first item selected
  const itemSelected = items.find(item => item.id === $('#item-list').val());
  // remove the item selected from the list of available items
  let availableItems = items.filter(item => item.id !== itemSelected.id);
  // update the list of available items keeping just the ones that share the same type
  availableItems = availableItems.filter(item => item.item_type === itemSelected.item_type);

  var newItem = `
    <div class="row mb-3 request-items">
      <div class="col">
        <label for="user" class="form-label">Requested Items:</label>
      </div>
      <div class="col">
        <select id="items-list" name="items[]" class="form-select" aria-label="Item" required>
          <option value="" selected>Select an item</option>
          ${availableItems.map(item => `<option value="${item.id}">${item.item}</option>`)}
        </select>
      </div>
      <div class="col d-flex gap-2 flex-wrap ">
        <button type="button" class="btn btn-primary" onclick="addItem()">Add Item</button>
        <button type="button" class="btn btn-danger" onclick="removeItem(this)">Remove</button>
      </div>
    </div>
  `;
  $('#repeater').append(newItem);
}

function removeItem(item) {
  $(item).closest('.row').remove();
}

function showEditModal(id) {
  $('#editModal').modal('show');
  $('#editModal').find('#edit-item').val(id);
  $('#edit-repeater').html('');

  getUsers();
  getItems();

  // fetch data from endpoint
  fetch(`https://juanmartinez.dev/inventory-manager/api.php?endpoint=requests&id=${id}`)
    .then(response => response.json())
    .then(response => {
      const data = response.data;
      console.log({ data, user: data.requested_by })

      const select = $('#editModal').find('#edit-user-list');
      users.forEach(user => {
        const option = `<option value="${user.usr_id}">${user.name}</option>`;
        select.append(option);
      });
      select.val(data.requested_by);

      const itemIds = data.items.map(item => Object.keys(item)[0])

      const itemDetailed = items.filter(item => itemIds.includes(item.id.toString()));

      // the list of available items keeping just the ones that share the same type
      const availableItems = items.filter(item => item.item_type === itemDetailed[0].item_type);

      // populate form with data from endpoint
      itemDetailed.forEach(item => {
        const newItem = `
          <div class="row mb-3 request-items">
            <div class="col">
              <label for="user" class="form-label">Requested Items:</label>
            </div>
            <div class="col">
              <select id="items-list" name="items[]" class="form-select" aria-label="Item" required>
                <option value="" selected>Select an item</option>
                ${availableItems.map(availableItem => `<option value="${availableItem.id}" ${availableItem.id === item.id ? 'selected' : ''}>${availableItem.item}</option>`)}
              </select>
            </div>
            <div class="col d-flex gap-2 flex-wrap">
              <button type="button" class="btn btn-primary" onclick="addItem()">Add Item</button>
              <button type="button" class="btn btn-danger" onclick="removeItem(this)">Remove</button>
            </div>
          </div>
        `;
        $('#edit-repeater').append(newItem);
        console.log({ newItem });
      });
    })
    .catch(error => console.error(error))
    .finally(() => {

    });
}

function showDeleteModal(id) {
  $('#deleteModal').modal('show');
  $('#deleteModal').find('#delete-item').val(id);
}