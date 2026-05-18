            $.get("get-fan.php", function(data, status){
                $("#fan").html(data);
            });
            $.get("get-fan.php", function(data, status){
                // $("#fan").html(data);
                
            });
            $('#register').click(function(){
                let fio = $('#fio').val();
                if(fio.length == 0){
                    swal("Iltimos ismingizni kiriting");
                    return false;
                }
                // let sana = $('#sana').val();
                // if(sana == ""){
                //     swal("Iltimos tug'iligan sanani kiriting");
                //     return false;
                // }
                // let telefon = $('#telefon').val();
                // if(telefon.length != 17){
                //     swal("Iltimos telefonni kiriting");
                //     return false;
                // }
                let fakultet = $('#fakultet').val();
                if(fakultet.length < 10){
                    swal("Iltimos fakultetni va yo'nalishni to'liq kiriting");
                    return false;
                }
                // let fan = $('#fan').val();
                // if(fan == 0){
                //     swal("Iltimos fanni tanlang");
                //     return false;
                // }
                let k = 0;
                let dat = $('#regform').serialize();
                swal({
                    title: "Ishonchinngiz komilmi, har bir qurilmadan faqat bitta imkoniyat beriladi, ma'lumotlarni tahrirlab bo'lmaydi?",
                    text: "Bu jarayonga shaxsan siz javobgarsiz.",
                    icon: "warning",
                    buttons: {
                        cancel: "Yo'q!",
                        catch: {
                          text: "Ha tasdiqlayman!",
                          value: "ha",
                        },
                    },
                    //dangerMode: true,
                })
                .then((willDelete) => {
                    if (willDelete=="ha") {
                        $.ajax({
                            url : "register.php",
                            type : "post",
                            data: dat,
                            success:function(data){
                                console.log(data);
                                var obj = jQuery.parseJSON(data);
                                if(obj.xatolik==0){
                                    swal("Tabriklaymiz!", obj.xabar, {
                                        icon: "success",
                                    });
                                    // $('#regform')[0].reset();
                                }
                                else{
                                    swal("Xatolik!", obj.xabar, {
                                        icon: "error",
                                    });
                                }
                            },
                            error:function(xhr){
                                alert("Kechirasiz internetda uzilish ro'y berdi iltimos qaytadan urining");
                            }
                        });
                    } 
                    else {
                        swal("Iltimos qaytadan tekshiring!");
                    }
                }); 
                // alert(dat);
            });