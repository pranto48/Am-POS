@extends('layouts.install', ['no_header' => 1])
@section('title', 'POS Installation - Update')

@section('content')
<div class="container">
    <div class="row">

        <div class="col-md-8 col-md-offset-2">
            <br/><br/>
          <div class="box box-primary active">
            <!-- /.box-header -->
            <div class="box-body">

              @if(session('error'))
                <div class="alert alert-danger">
                    {!! session('error') !!}
                </div>
              @endif

              @if ($errors->any())
                <div class="alert alert-danger">
                  <ul>
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                  </ul>
                </div>
              @endif

              <form class="form" id="details_form" method="post" 
                      action="{{route('install.update')}}">
                    {{ csrf_field() }}

                    <h4> License Details <small class="text-danger">Make sure to provide correct information from IT Support BD Portal</small></h4>
                    <hr/>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="envato_purchase_code">Application License Key:*</label>
                            <input type="text" name="LICENSE_KEY" required class="form-control" id="envato_purchase_code" placeholder="License Key">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="envato_username">Client ID (User ID):*</label>
                            <input type="text" name="CLIENT_ID" required class="form-control" id="envato_username" placeholder="Your Client ID / User ID">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                          <label for="envato_email">Client Email:</label>
                          <input type="email" name="CLIENT_EMAIL" class="form-control" id="envato_email" placeholder="optional (Your Portal Email)">
                          <p class="help-block">For Newsletter & support</p>
                        </div>
                    </div>
                    @include('install.partials.i_service')
                    @include('install.partials.e_license')

                    <div class="col-md-12">
                        <button type="submit" id="install_button" class="btn btn-primary pull-right">I Agree, Update</button>
                    </div>
              </form>
            </div>
          <!-- /.box-body -->
          </div>
        </div>

    </div>
</div>
@endsection

@section('javascript')
  <script type="text/javascript">
    $(document).ready(function(){
      $('select#MAIL_MAILER').change(function(){
        var driver = $(this).val();

        if(driver == 'smtp'){
          $('div.smtp').removeClass('hide');
          $('input.smtp_input').attr('disabled', false);
        } else {
          $('div.smtp').addClass('hide');
          $('input.smtp_input').attr('disabled', true);
        }
      })

      $('form#details_form').submit(function(){
        $('button#install_button').attr('disabled', true).text('Installing...');
        $('div.install_msg').removeClass('hide');
        $('.back_button').hide();
      });

    })
  </script>
@endsection