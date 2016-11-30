import React, { Component } from 'react'
import ScrollPosition from '../helper/ScrollPosition'

export default class Posts extends Component {
  constructor() {
    super()
    this.state = {
      posts: []
    }
    this.isloading = false
    this.hasReachedEnd = false
    this.postNodes = {}
  }

  componentWillMount() {
    // TODO Handle errors
    jQuery.getJSON(this.props.getURL, (posts) => {
      this.setState({
        posts: posts.content
      })
      this._handleAssets(posts.libraries, posts.commands, document.body)
    })

    addEventListener('scroll', this._lazyload.bind(this))
  }

  componentWillUnmount() {
    removeEventListener('scroll', this._lazyload.bind(this))
  }

  _lazyload() {
    var el = this._getLastElement()
    if (!this.isloading && !this.hasReachedEnd && el && this._elementInViewport(el)) {
      this.isloading = true
      this._loadNextPosts()
    }
  }
  _getLastElement() {
    return this.postsWrapper.querySelector('div.liveblog-post:last-child')
  }
  _elementInViewport(el) {
    var rect = el.getBoundingClientRect()

    return (
         rect.top   >= 0
      && rect.left  >= 0
      && rect.top <= (window.innerHeight || document.documentElement.clientHeight)
    )
  }
  _loadNextPosts() {
    var posts = this.state.posts
    var lastPost = posts[posts.length-1]
    var url = this.props.getNextURL.replace('%s', lastPost.created)
    // TODO: error handling
    jQuery.getJSON(url, (lazyPosts) => {
      if (lazyPosts && Array.isArray(lazyPosts.content)) {
        if (lazyPosts.content.length != 0) {
          this.setState({
            posts: [
              ...this.state.posts,
              ...lazyPosts.content
            ]
          })
          this._handleAssets(lazyPosts.libraries, lazyPosts.commands, document.body)
        }
        else {
          this.hasReachedEnd = true
        }
      }

      this.isloading = false
    })
  }

  _handleAssets(libraries, commands, context) {
    this.props.assetHandler.loadLibraries(libraries)
    this.props.assetHandler.executeCommands(commands)
    this.props.assetHandler.afterLoading(context)
  }

  addPost(post) {
    var scrollPosition = new ScrollPosition(document.body, this.postsWrapper)
    scrollPosition.prepareFor('up')
    this.setState({
      posts: [
          post,
          ...this.state.posts
      ]
    })
    this._handleAssets(post.libraries, post.commands, document.body)
    scrollPosition.restore()
  }

  editPost(editedPost) {
    var found = false
    var posts = this.state.posts.map((post) => {
      if (post.id == editedPost.id) {
        found = true
        return editedPost
      }
      else {
        return post;
      }
    })

    if (found) {
      var scrollPosition = new ScrollPosition(document.body, this.postNodes[editedPost.id])
      scrollPosition.prepareFor('up')

      this.setState({
        posts: posts
      })

      this._handleAssets(post.libraries, post.commands, document.body)
      scrollPosition.restore()
    }
  }

  render() {
    return (
      <div className="liveblog-posts-wrapper" ref={(wrapper) => this.postsWrapper = wrapper}>
        { this.state.posts.map((post) => {
          return (
            <div className="liveblog-post" key={post.id} ref={(node) => { this.postNodes[post.id] = node }}>
              <div dangerouslySetInnerHTML={{ __html: post.content }} />
            </div>
          )
        })}
      </div>
    )
  }


}