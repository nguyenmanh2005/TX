$(document).ready(function() {
    let allPets = [];
    let myPets = [];
    let activePet = null;

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function loadPets() {
        $.get('../api_pets.php?action=get_all', function(res1) {
            if (res1.success) {
                allPets = res1.pets;
                $.get('../api_pets.php?action=get_my_pets', function(res2) {
                    if (res2.success) {
                        myPets = res2.my_pets;
                        activePet = res2.active_pet;
                        renderPets();
                    }
                }, 'json');
            }
        }, 'json');
    }

    function isOwned(petId) {
        return myPets.some(p => p.id == petId);
    }

    function renderPets() {
        let html = '';
        allPets.forEach(pet => {
            let owned = isOwned(pet.id);
            let isActive = activePet && activePet.id == pet.id;
            
            // Note: DB has image_url, we used it for emojis in the new ones, or paths in the old ones.
            // If it starts with img/ it's an image, else it's an emoji.
            let iconHtml = pet.image_url.startsWith('img/') ? `<img src="../${pet.image_url}" class="pet-img" />` : pet.image_url;

            let actionHtml = '';
            if (isActive) {
                actionHtml = `
                    <button class="btn-pet btn-equipped"><i class="fas fa-check"></i> Đang Trang Bị</button>
                    <button class="btn-pet btn-unequip" onclick="unequip()"><i class="fas fa-times"></i> Tháo Cất</button>
                `;
            } else if (owned) {
                actionHtml = `<button class="btn-pet btn-equip" onclick="equip(${pet.id})"><i class="fas fa-hand-sparkles"></i> Trang Bị</button>`;
            } else {
                actionHtml = `<button class="btn-pet btn-buy" onclick="buyPet(${pet.id})">Mua - ${formatNumber(pet.price)} GTLM</button>`;
            }

            html += `
                <div class="pet-card" style="box-shadow: 0 4px 15px ${isActive ? 'rgba(16, 185, 129, 0.4)' : 'rgba(0,0,0,0.5)'}; border-color: ${isActive ? '#10b981' : 'rgba(255,255,255,0.1)'}">
                    <div>
                        <div class="pet-icon">${iconHtml}</div>
                        <div class="pet-name">${pet.name}</div>
                        <div class="pet-desc">${pet.description || 'Chưa có mô tả.'}</div>
                    </div>
                    <div>
                        ${actionHtml}
                    </div>
                </div>
            `;
        });
        $('#petsList').html(html);
    }

    window.buyPet = function(petId) {
        Swal.fire({
            title: 'Mua Thú Cưng?',
            text: "Xác nhận dùng GTLM để mua Thú cưng này!",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Đồng ý Mua',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api_pets.php', { action: 'buy', pet_id: petId }, function(res) {
                    if (res.success) {
                        $('#userMoney').text(formatNumber(res.new_money));
                        Swal.fire({ icon: 'success', title: 'Thành công', text: res.message, background: '#111', color: '#fff' });
                        loadPets();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Lỗi', text: res.message, background: '#111', color: '#fff' });
                    }
                }, 'json');
            }
        });
    };

    window.equip = function(petId) {
        $.post('../api_pets.php', { action: 'equip', pet_id: petId }, function(res) {
            if (res.success) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.message, showConfirmButton: false, timer: 1500 });
                loadPets();
            } else {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: res.message, background: '#111', color: '#fff' });
            }
        }, 'json');
    };

    window.unequip = function() {
        $.post('../api_pets.php', { action: 'unequip' }, function(res) {
            if (res.success) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.message, showConfirmButton: false, timer: 1500 });
                loadPets();
            }
        }, 'json');
    };

    loadPets();
});
