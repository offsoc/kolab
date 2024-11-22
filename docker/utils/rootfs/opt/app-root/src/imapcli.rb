#!/usr/bin/env ruby

require "net/imap"
require 'thor'

class IMAP < Net::IMAP

    def send_command2(cmd, *args, &block)
      synchronize do
        tag = generate_tag
        put_string(tag + " " + cmd + CRLF)

        begin
          return get_tagged_response(tag, cmd)
        ensure
          if block
            remove_response_handler(block)
          end
        end
      end
    end

    def getmetadata(mailbox, *entries)
      synchronize do
        data = '(' + entries.join(' ') + ')'
        send_command("GETMETADATA" + " (DEPTH infinity)", mailbox, RawData.new(data))
        
        result = @responses.delete("METADATA")
        if result and result.length() > 0
          return result[-1]
        end
        return ""
      end
    end

    def setmetadata(mailbox, entry, value)
      data = '(' + entry + ' ' + '"' + IMAP.encode_utf7(value) + '"' + ')'
      send_command("SETMETADATA", mailbox, RawData.new(data))
    end
end


class ImapCli < Thor
  class_option :host
  class_option :port
  class_option :username
  class_option :login_as
  class_option :password
  class_option :ssl, :type => :boolean
  class_option :debug, :type => :boolean

  no_commands {
    def imap()
      if !@imap
        if options[:ssl]
          @imap = IMAP.new(options[:host], :port => options[:port], :ssl => {
            :verify_mode => OpenSSL::SSL::VERIFY_NONE
          })
        else
          @imap = IMAP.new(options[:host], :port => options[:port], :ssl => false)
        end
        if options[:debug]
          IMAP.debug = true
        end
        if  options[:login_as]
          @imap.authenticate("PLAIN", options[:username], options[:password], authzid: options[:login_as])
        else
          @imap.authenticate("PLAIN", options[:username], options[:password])
        end
      end
      @imap
    end
  }

  desc "login", "Login."
  def login()
    imap()
  end

  desc "list", "List."
  def list(folder = "**")
    p imap.list("", folder)
  end

  desc "search", "Search."
  def search(folder, query, location="BODY")
    p imap.select(folder)
    imap.search([location, query])
  end

  desc "lsub", "List subscriptions."
  def lsub(folder = "**")
    p imap.lsub("", folder)
  end

  desc "namespace", "Namespace."
  def namespace()
    p imap.namespace()
  end

  desc "capability", "Capability."
  def capability()
    p imap.capability()
  end

  desc "select", "Select."
  def select(folder)
    p imap.select(folder)
  end

  desc "create", "Create."
  def create(folder)
    p imap.create(folder)
  end

  desc "delete", "Delete."
  def delete(folder)
    p imap.delete(folder)
  end

  desc "subscribe", "Subscribe."
  def subscribe(folder)
    p imap.subscribe(folder)
  end

  desc "getmetadata", "Getmetadata."
  def getmetadata(folder, *entries)
    # p imap.select(folder)
    p imap.getmetadata(folder, entries)
  end

  desc "idle", "IDLE."
  def idle(folder, *entries)
    p imap.select(folder)
    imap.send_command2("IDLE")
  end

  desc "getacl", "getacl."
  def getacl(folder)
    p imap.getacl(folder)
  end

  desc "setacl", "setacl."
  def setacl(folder, user, rights)
    p imap.setacl(folder, user, rights)
  end

  desc "setmetadata", "Setmetadata."
  def setmetadata(folder, entry, value)
    p imap.setmetadata(folder, entry, value)
  end

  desc "append", "APPEND."
  def append(folder, filepath)
    file = File.open(filepath)
    file_data = file.read
    p imap.append(folder, file_data)
  end

  desc "download", "Download."
  def download(folder, destination)
    imap.select(folder)
    #Dir.mkdir destination unless File.exists? destination
    Dir.mkdir destination
    imap.uid_fetch(1..-1, "RFC822").each do |mail|
      uid = mail.attr["UID"]
      p uid
      File.write("#{destination}#{uid}.", mail.attr["RFC822"])
    end
  end

end

begin
    ImapCli.start(ARGV)
rescue => e
    puts e.message
    puts e.backtrace.inspect
    raise e
end
